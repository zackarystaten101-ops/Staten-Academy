/**
 * Booking Service
 * Handles class bookings, slot requests, recurring bookings, and cancellations
 */

import { query, transaction } from '../config/database.js';
import { v4 as uuidv4 } from 'uuid';
import { holdEntitlement, confirmEntitlementUse, refundEntitlement, EntitlementType } from './WalletService.js';
import { calculateEarnings, createEarningsRecord } from './EarningsService.js';
import { ensureUTC } from '../utils/timezone.js';
import type pg from 'pg';

export interface SlotRequest {
  id: string;
  student_id: number;
  teacher_id: number;
  requested_start_utc: Date;
  requested_end_utc: Date;
  status: 'pending' | 'accepted' | 'declined' | 'expired';
  entitlement_hold_id: string | null;
  created_at: Date;
  expires_at: Date | null;
}

export interface Class {
  id: string;
  type: 'one_on_one' | 'group' | 'practice' | 'time_off' | 'video_session';
  title: string | null;
  start_at_utc: Date;
  end_at_utc: Date;
  status: 'requested' | 'confirmed' | 'cancelled' | 'completed' | 'no_show';
  teacher_id: number;
  student_id: number | null;
  slot_request_id: string | null;
  entitlement_id: string | null;
  earnings_record_id: string | null;
  recurrence_group_id: string | null;
  meta: Record<string, any>;
  created_at: Date;
  updated_at: Date;
}

/**
 * Request a slot (student initiates booking)
 * Creates a hold on entitlement and a slot request
 */
export async function requestSlot(
  studentId: number,
  teacherId: number,
  startUtc: Date | string,
  endUtc: Date | string,
  entitlementType: EntitlementType = 'one_on_one_class',
  existingClient?: pg.PoolClient
): Promise<{ slotRequestId: string; entitlementHoldId: string }> {
  const start = ensureUTC(startUtc);
  const end = ensureUTC(endUtc);
  
  const execute = async (client: pg.PoolClient) => {
    // Check teacher availability (with SELECT FOR UPDATE to prevent double-booking)
    const availabilityCheck = await client.query(
      `SELECT id FROM availability_slots
       WHERE teacher_id = $1 
       AND is_open = TRUE
       AND start_at_utc <= $2
       AND end_at_utc >= $3
       FOR UPDATE SKIP LOCKED
       LIMIT 1`,
      [teacherId, start, end]
    );
    
    if (availabilityCheck.rows.length === 0) {
      throw new Error('Teacher not available at this time');
    }
    
    // Check for conflicts with existing classes
    const conflictCheck = await client.query(
      `SELECT id FROM classes
       WHERE teacher_id = $1
       AND status IN ('requested', 'confirmed')
       AND (
         (start_at_utc <= $2 AND end_at_utc > $2)
         OR (start_at_utc < $3 AND end_at_utc >= $3)
         OR (start_at_utc >= $2 AND end_at_utc <= $3)
       )`,
      [teacherId, start, end]
    );
    
    if (conflictCheck.rows.length > 0) {
      throw new Error('Time slot already booked');
    }
    
    // Hold entitlement
    const entitlementHoldId = await holdEntitlement(studentId, entitlementType, uuidv4(), client);
    
    // Create slot request
    const expiresAt = new Date(Date.now() + 15 * 60 * 1000); // 15 minutes TTL
    
    const slotRequestResult = await client.query(
      `INSERT INTO slot_requests 
       (student_id, teacher_id, requested_start_utc, requested_end_utc, entitlement_hold_id, expires_at)
       VALUES ($1, $2, $3, $4, $5, $6)
       RETURNING id`,
      [studentId, teacherId, start, end, entitlementHoldId, expiresAt]
    );
    
    const slotRequestId = slotRequestResult.rows[0].id;
    
    // Create initial class record with 'requested' status
    await client.query(
      `INSERT INTO classes 
       (type, title, start_at_utc, end_at_utc, status, teacher_id, student_id, slot_request_id, entitlement_id)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
       RETURNING id`,
      [
        entitlementType === 'one_on_one_class' ? 'one_on_one' : 'group',
        'Lesson',
        start,
        end,
        'requested',
        teacherId,
        studentId,
        slotRequestId,
        entitlementHoldId,
      ]
    );
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        studentId,
        'slot_requested',
        'slot_request',
        slotRequestId,
        JSON.stringify({ teacher_id: teacherId, start: start.toISOString(), end: end.toISOString() }),
      ]
    );
    
    return { slotRequestId, entitlementHoldId };
  };
  
  if (existingClient) {
    return execute(existingClient);
  } else {
    return transaction(execute);
  }
}

/**
 * Accept slot request (teacher accepts booking)
 */
export async function acceptSlotRequest(
  requestId: string,
  teacherId: number,
  existingClient?: pg.PoolClient
): Promise<{ classId: string; earningsId: string | null }> {
  const execute = async (client: pg.PoolClient) => {
    // Get slot request with lock
    const requestResult = await client.query(
      `SELECT * FROM slot_requests 
       WHERE id = $1 AND teacher_id = $2 AND status = 'pending'
       FOR UPDATE`,
      [requestId, teacherId]
    );
    
    if (requestResult.rows.length === 0) {
      throw new Error('Slot request not found or already processed');
    }
    
    const request = requestResult.rows[0];
    
    // Check if expired
    if (request.expires_at && new Date(request.expires_at) < new Date()) {
      // Release entitlement hold
      if (request.entitlement_hold_id) {
        await refundEntitlement(
          request.entitlement_hold_id,
          requestId,
          'Slot request expired',
          teacherId,
          client
        );
      }
      
      await client.query(
        `UPDATE slot_requests SET status = 'expired' WHERE id = $1`,
        [requestId]
      );
      
      throw new Error('Slot request has expired');
    }
    
    // Verify availability again (with lock)
    const availabilityCheck = await client.query(
      `SELECT id FROM availability_slots
       WHERE teacher_id = $1 
       AND is_open = TRUE
       AND start_at_utc <= $2
       AND end_at_utc >= $3
       FOR UPDATE SKIP LOCKED`,
      [teacherId, request.requested_start_utc, request.requested_end_utc]
    );
    
    if (availabilityCheck.rows.length === 0) {
      throw new Error('Teacher no longer available at this time');
    }
    
    // Confirm entitlement use
    if (request.entitlement_hold_id) {
      await confirmEntitlementUse(request.entitlement_hold_id, requestId, request.student_id, client);
    }
    
    // Update class to confirmed
    const classResult = await client.query(
      `UPDATE classes 
       SET status = 'confirmed', updated_at = NOW()
       WHERE slot_request_id = $1
       RETURNING id`,
      [requestId]
    );
    
    if (classResult.rows.length === 0) {
      throw new Error('Class record not found');
    }
    
    const classId = classResult.rows[0].id;
    
    // Calculate and create earnings record
    const earningsId = await createEarningsRecord(classId, teacherId, request.student_id);
    
    // Update class with earnings record ID
    await client.query(
      `UPDATE classes SET earnings_record_id = $1 WHERE id = $2`,
      [earningsId, classId]
    );
    
    // Update slot request status
    await client.query(
      `UPDATE slot_requests SET status = 'accepted', updated_at = NOW() WHERE id = $1`,
      [requestId]
    );
    
    // Mark availability slot as used (optional - you might want to keep it open for other bookings)
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        teacherId,
        'slot_accepted',
        'slot_request',
        requestId,
        JSON.stringify({ class_id: classId }),
      ]
    );
    
    return { classId, earningsId };
  };
  
  if (existingClient) {
    return execute(existingClient);
  } else {
    return transaction(execute);
  }
}

/**
 * Decline slot request (teacher declines)
 */
export async function declineSlotRequest(
  requestId: string,
  teacherId: number,
  reason?: string
): Promise<void> {
  await transaction(async (client) => {
    const requestResult = await client.query(
      `SELECT * FROM slot_requests 
       WHERE id = $1 AND teacher_id = $2 AND status = 'pending'
       FOR UPDATE`,
      [requestId, teacherId]
    );
    
    if (requestResult.rows.length === 0) {
      throw new Error('Slot request not found or already processed');
    }
    
    const request = requestResult.rows[0];
    
    // Release entitlement hold
    if (request.entitlement_hold_id) {
      await refundEntitlement(
        request.entitlement_hold_id,
        requestId,
        reason || 'Teacher declined',
        teacherId,
        client
      );
    }
    
    // Update slot request
    await client.query(
      `UPDATE slot_requests SET status = 'declined', updated_at = NOW() WHERE id = $1`,
      [requestId]
    );
    
    // Update class status
    await client.query(
      `UPDATE classes SET status = 'cancelled' WHERE slot_request_id = $1`,
      [requestId]
    );
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        teacherId,
        'slot_declined',
        'slot_request',
        requestId,
        JSON.stringify({ reason: reason || 'Teacher declined' }),
      ]
    );
  });
}

/**
 * Cancel a class
 * Handles cancellation rules: 24h policy, refunds, teacher pay
 */
export async function cancelClass(
  classId: string,
  cancelledBy: number,
  cancelledByRole: 'student' | 'teacher' | 'admin',
  reason?: string
): Promise<void> {
  await transaction(async (client) => {
    const classResult = await client.query(
      `SELECT * FROM classes WHERE id = $1 FOR UPDATE`,
      [classId]
    );
    
    if (classResult.rows.length === 0) {
      throw new Error('Class not found');
    }
    
    const classRecord = classResult.rows[0];
    
    if (classRecord.status === 'cancelled' || classRecord.status === 'completed') {
      throw new Error('Class cannot be cancelled');
    }
    
    const now = new Date();
    const classStart = new Date(classRecord.start_at_utc);
    const hoursUntilClass = (classStart.getTime() - now.getTime()) / (1000 * 60 * 60);
    
    let shouldRefundEntitlement = false;
    let teacherGetsPaid = false;
    
    if (cancelledByRole === 'teacher') {
      // Teacher cancels: try replacement, if no replacement → refund
      // For now, always refund (replacement logic can be added later)
      shouldRefundEntitlement = true;
      
      // Notify admin (can be added to notifications system)
    } else if (cancelledByRole === 'student') {
      // Student cancels: 24h rule
      if (hoursUntilClass >= 24) {
        shouldRefundEntitlement = true;
      } else {
        teacherGetsPaid = true;
      }
    } else if (cancelledByRole === 'admin') {
      // Admin cancels: typically refund (unless otherwise specified)
      shouldRefundEntitlement = true;
    }
    
    // Update class status
    await client.query(
      `UPDATE classes SET status = 'cancelled', updated_at = NOW() WHERE id = $1`,
      [classId]
    );
    
    // Refund entitlement if applicable
    if (shouldRefundEntitlement && classRecord.entitlement_id) {
      await refundEntitlement(
        classRecord.entitlement_id,
        classId,
        reason || 'Class cancelled',
        cancelledBy,
        client
      );
    }
    
    // Handle earnings (if teacher gets paid, earnings stay; otherwise cancel)
    if (!teacherGetsPaid && classRecord.earnings_record_id) {
      await client.query(
        `UPDATE earnings SET payout_status = 'pending' WHERE id = $1`,
        [classRecord.earnings_record_id]
      );
      // Note: You might want to mark as cancelled instead
    }
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        cancelledBy,
        'class_cancelled',
        'class',
        classId,
        JSON.stringify({
          reason,
          refunded: refundEntitlement,
          teacher_gets_paid: teacherGetsPaid,
          hours_until_class: hoursUntilClass,
        }),
      ]
    );
  });
}

/**
 * Mark class as completed
 */
export async function completeClass(classId: string, teacherId: number): Promise<void> {
  await query(
    `UPDATE classes 
     SET status = 'completed', updated_at = NOW()
     WHERE id = $1 AND teacher_id = $2`,
    [classId, teacherId]
  );
}

/**
 * Mark no-show
 */
export async function markNoShow(
  classId: string,
  markedBy: number,
  markedByRole: 'student' | 'teacher' | 'admin',
  noShowType: 'student' | 'teacher'
): Promise<void> {
  await transaction(async (client) => {
    const classResult = await client.query(
      `SELECT * FROM classes WHERE id = $1 FOR UPDATE`,
      [classId]
    );
    
    if (classResult.rows.length === 0) {
      throw new Error('Class not found');
    }
    
    const classRecord = classResult.rows[0];
    
    if (noShowType === 'student') {
      // Student no-show: teacher gets paid, class marked as completed
      await client.query(
        `UPDATE classes SET status = 'completed', updated_at = NOW() WHERE id = $1`,
        [classId]
      );
      // Earnings already created, so teacher gets paid
    } else if (noShowType === 'teacher') {
      // Teacher no-show: student gets refunded
      await client.query(
        `UPDATE classes SET status = 'cancelled', updated_at = NOW() WHERE id = $1`,
        [classId]
      );
      
      if (classRecord.entitlement_id) {
        await refundEntitlement(
          classRecord.entitlement_id,
          classId,
          'Teacher no-show',
          markedBy,
          client
        );
      }
      
      // Cancel earnings
      if (classRecord.earnings_record_id) {
        await client.query(
          `UPDATE earnings SET payout_status = 'pending' WHERE id = $1`,
          [classRecord.earnings_record_id]
        );
      }
      
      // Notify admin (can be added)
    }
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        markedBy,
        'no_show',
        'class',
        classId,
        JSON.stringify({ no_show_type: noShowType }),
      ]
    );
  });
}

