/**
 * Recurring Booking Service
 * Handles weekly recurring lesson series with payment failure handling
 */

import { query, transaction } from '../config/database.js';
import { v4 as uuidv4 } from 'uuid';
import { requestSlot, acceptSlotRequest } from './BookingService.js';
import { EntitlementType } from './WalletService.js';

export interface RecurrenceGroup {
  id: string;
  student_id: number;
  teacher_id: number;
  day_of_week: number; // 0=Sunday, 6=Saturday
  start_time: string;
  end_time: string;
  start_date: Date;
  end_date: Date | null;
  frequency_weeks: number;
  status: 'active' | 'paused' | 'cancelled';
  payment_failures: number;
  meta: Record<string, any>;
}

/**
 * Create a recurring weekly booking series
 */
export async function createRecurringSeries(
  studentId: number,
  teacherId: number,
  dayOfWeek: number,
  startTime: string,
  endTime: string,
  startDate: Date,
  endDate: Date | null,
  entitlementType: EntitlementType = 'one_on_one_class'
): Promise<{ recurrenceGroupId: string; classIds: string[] }> {
  return transaction(async (client) => {
    // Create recurrence group
    const recurrenceResult = await client.query(
      `INSERT INTO recurrence_groups 
       (student_id, teacher_id, day_of_week, start_time, end_time, start_date, end_date, frequency_weeks, status)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
       RETURNING id`,
      [studentId, teacherId, dayOfWeek, startTime, endTime, startDate, endDate || null, 1, 'active']
    );
    
    const recurrenceGroupId = recurrenceResult.rows[0].id;
    
    // Generate classes for next 8 weeks (or until end_date)
    const classIds: string[] = [];
    const currentDate = new Date(startDate);
    const maxDate = endDate || new Date(Date.now() + 8 * 7 * 24 * 60 * 60 * 1000); // 8 weeks default
    
    let weekCount = 0;
    while (currentDate <= maxDate && weekCount < 52) { // Max 52 weeks
      // Calculate the date for this week's class
      const dayDiff = (dayOfWeek - currentDate.getDay() + 7) % 7;
      const classDate = new Date(currentDate);
      classDate.setDate(currentDate.getDate() + dayDiff);
      
      if (classDate > maxDate) break;
      
      // Create class for this week
      const [startHour, startMin] = startTime.split(':').map(Number);
      const [endHour, endMin] = endTime.split(':').map(Number);
      
      const startUtc = new Date(classDate);
      startUtc.setHours(startHour, startMin, 0, 0);
      
      const endUtc = new Date(classDate);
      endUtc.setHours(endHour, endMin, 0, 0);
      
      // Request slot (this will hold entitlement)
      try {
        const { slotRequestId, entitlementHoldId } = await requestSlot(
          studentId,
          teacherId,
          startUtc,
          endUtc,
          entitlementType,
          client
        );
        
        // Auto-accept if teacher has auto-accept enabled (for now, create as confirmed)
        // In production, you might want to check teacher settings
        const { classId } = await acceptSlotRequest(slotRequestId, teacherId, client);
        
        // Update class with recurrence_group_id
        await client.query(
          `UPDATE classes SET recurrence_group_id = $1 WHERE id = $2`,
          [recurrenceGroupId, classId]
        );
        
        classIds.push(classId);
      } catch (error) {
        // Skip this class if booking fails (might be availability issue)
        console.error(`Failed to create class for ${classDate.toISOString()}:`, error);
      }
      
      // Move to next week
      currentDate.setDate(currentDate.getDate() + 7);
      weekCount++;
    }
    
    return { recurrenceGroupId, classIds };
  });
}

/**
 * Generate future classes for a recurring series (called monthly or as needed)
 */
export async function generateFutureClassesForSeries(
  recurrenceGroupId: string,
  weeksAhead: number = 4
): Promise<string[]> {
  return transaction(async (client) => {
    const groupResult = await client.query(
      `SELECT * FROM recurrence_groups WHERE id = $1`,
      [recurrenceGroupId]
    );
    
    if (groupResult.rows.length === 0) {
      throw new Error('Recurrence group not found');
    }
    
    const group = groupResult.rows[0];
    
    if (group.status !== 'active') {
      throw new Error('Recurrence group is not active');
    }
    
    // Get the latest class date for this series
    const latestClassResult = await client.query(
      `SELECT MAX(start_at_utc) as latest_date 
       FROM classes 
       WHERE recurrence_group_id = $1`,
      [recurrenceGroupId]
    );
    
    const latestDate = latestClassResult.rows[0]?.latest_date
      ? new Date(latestClassResult.rows[0].latest_date)
      : new Date(group.start_date);
    
    const maxDate = group.end_date 
      ? new Date(group.end_date)
      : new Date(Date.now() + weeksAhead * 7 * 24 * 60 * 60 * 1000);
    
    const classIds: string[] = [];
    const currentDate = new Date(latestDate);
    currentDate.setDate(currentDate.getDate() + 7); // Start from next week
    
    let weekCount = 0;
    while (currentDate <= maxDate && weekCount < weeksAhead) {
      const dayDiff = (group.day_of_week - currentDate.getDay() + 7) % 7;
      const classDate = new Date(currentDate);
      classDate.setDate(currentDate.getDate() + dayDiff);
      
      if (classDate > maxDate) break;
      
      // Check if class already exists
      const existingCheck = await client.query(
        `SELECT id FROM classes 
         WHERE recurrence_group_id = $1 
         AND DATE(start_at_utc) = $2`,
        [recurrenceGroupId, classDate]
      );
      
      if (existingCheck.rows.length > 0) {
        currentDate.setDate(currentDate.getDate() + 7);
        weekCount++;
        continue; // Skip if already exists
      }
      
      // Create class (similar to createRecurringSeries)
      const [startHour, startMin] = group.start_time.split(':').map(Number);
      const [endHour, endMin] = group.end_time.split(':').map(Number);
      
      const startUtc = new Date(classDate);
      startUtc.setHours(startHour, startMin, 0, 0);
      
      const endUtc = new Date(classDate);
      endUtc.setHours(endHour, endMin, 0, 0);
      
      try {
        const { slotRequestId } = await requestSlot(
          group.student_id,
          group.teacher_id,
          startUtc,
          endUtc,
          'one_on_one_class',
          client
        );
        
        const { classId } = await acceptSlotRequest(slotRequestId, group.teacher_id, client);
        
        await client.query(
          `UPDATE classes SET recurrence_group_id = $1 WHERE id = $2`,
          [recurrenceGroupId, classId]
        );
        
        classIds.push(classId);
      } catch (error) {
        console.error(`Failed to generate class for ${classDate.toISOString()}:`, error);
      }
      
      currentDate.setDate(currentDate.getDate() + 7);
      weekCount++;
    }
    
    return classIds;
  });
}

/**
 * Handle payment failure for recurring series
 * If payment fails twice consecutively, cancel the series
 */
export async function handlePaymentFailure(
  recurrenceGroupId: string
): Promise<{ cancelled: boolean; paymentFailures: number }> {
  return transaction(async (client) => {
    const groupResult = await client.query(
      `SELECT payment_failures, status FROM recurrence_groups 
       WHERE id = $1
       FOR UPDATE`,
      [recurrenceGroupId]
    );
    
    if (groupResult.rows.length === 0) {
      throw new Error('Recurrence group not found');
    }
    
    const currentFailures = groupResult.rows[0].payment_failures || 0;
    const newFailures = currentFailures + 1;
    
    let cancelled = false;
    
    if (newFailures >= 2) {
      // Cancel the series after 2 consecutive failures
      await client.query(
        `UPDATE recurrence_groups 
         SET status = 'cancelled', payment_failures = $1, updated_at = NOW()
         WHERE id = $2`,
        [newFailures, recurrenceGroupId]
      );
      
      // Cancel future classes
      await client.query(
        `UPDATE classes 
         SET status = 'cancelled'
         WHERE recurrence_group_id = $1 
         AND status IN ('requested', 'confirmed')
         AND start_at_utc > NOW()`,
        [recurrenceGroupId]
      );
      
      cancelled = true;
    } else {
      // Increment failure count but keep active
      await client.query(
        `UPDATE recurrence_groups 
         SET payment_failures = $1, updated_at = NOW()
         WHERE id = $2`,
        [newFailures, recurrenceGroupId]
      );
    }
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        0, // System actor
        cancelled ? 'recurring_series_cancelled' : 'payment_failure_recorded',
        'recurrence_group',
        recurrenceGroupId,
        JSON.stringify({ payment_failures: newFailures, cancelled }),
      ]
    );
    
    return { cancelled, paymentFailures: newFailures };
  });
}

/**
 * Reset payment failures (when payment succeeds)
 */
export async function resetPaymentFailures(recurrenceGroupId: string): Promise<void> {
  await query(
    `UPDATE recurrence_groups 
     SET payment_failures = 0, updated_at = NOW()
     WHERE id = $1`,
    [recurrenceGroupId]
  );
}

/**
 * Pause recurring series
 */
export async function pauseRecurringSeries(recurrenceGroupId: string): Promise<void> {
  await query(
    `UPDATE recurrence_groups 
     SET status = 'paused', updated_at = NOW()
     WHERE id = $1`,
    [recurrenceGroupId]
  );
}

/**
 * Resume recurring series
 */
export async function resumeRecurringSeries(recurrenceGroupId: string): Promise<void> {
  await query(
    `UPDATE recurrence_groups 
     SET status = 'active', updated_at = NOW()
     WHERE id = $1`,
    [recurrenceGroupId]
  );
}

