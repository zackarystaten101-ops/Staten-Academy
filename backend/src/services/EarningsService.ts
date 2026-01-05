/**
 * Earnings Service
 * Handles teacher earnings calculation and tracking
 * CRITICAL: Earnings data is NOT visible to students (sanitized in middleware)
 */

import { query, transaction } from '../config/database.js';
import { v4 as uuidv4 } from 'uuid';

export interface Earnings {
  id: string;
  class_id: string | null;
  teacher_id: number;
  amount: number;
  platform_fee: number;
  payout_status: 'pending' | 'paid';
  paid_at: Date | null;
  created_at: Date;
}

/**
 * Get teacher's default rate from teacher_profiles
 */
async function getTeacherRate(teacherId: number): Promise<number> {
  const result = await query(
    `SELECT default_rate FROM teacher_profiles WHERE teacher_id = $1`,
    [teacherId]
  );
  
  if (result.rows.length === 0) {
    // Create default profile with $15/hour
    await query(
      `INSERT INTO teacher_profiles (teacher_id, default_rate) 
       VALUES ($1, $2)
       ON CONFLICT (teacher_id) DO NOTHING`,
      [teacherId, 15.00]
    );
    return 15.00;
  }
  
  return parseFloat(result.rows[0].default_rate) || 15.00;
}

/**
 * Calculate earnings for a class
 * Per requirements: $15/hour base rate, no platform fees
 */
export async function calculateEarnings(
  classId: string,
  teacherId: number
): Promise<number> {
  // Get class duration
  const classResult = await query(
    `SELECT start_at_utc, end_at_utc, type FROM classes WHERE id = $1`,
    [classId]
  );
  
  if (classResult.rows.length === 0) {
    throw new Error('Class not found');
  }
  
  const classRecord = classResult.rows[0];
  const start = new Date(classRecord.start_at_utc);
  const end = new Date(classRecord.end_at_utc);
  const durationHours = (end.getTime() - start.getTime()) / (1000 * 60 * 60);
  
  // Get teacher rate (can be different for group classes)
  let rate = await getTeacherRate(teacherId);
  
  // Check if group class has different rate
  if (classRecord.type === 'group') {
    const profileResult = await query(
      `SELECT group_class_rate FROM teacher_profiles WHERE teacher_id = $1`,
      [teacherId]
    );
    
    if (profileResult.rows.length > 0 && profileResult.rows[0].group_class_rate) {
      rate = parseFloat(profileResult.rows[0].group_class_rate) || rate;
    }
  }
  
  // Calculate: rate * hours (no platform fee per requirements)
  const amount = rate * durationHours;
  
  return Math.round(amount * 100) / 100; // Round to 2 decimal places
}

/**
 * Create earnings record for a class
 */
export async function createEarningsRecord(
  classId: string,
  teacherId: number,
  studentId: number
): Promise<string> {
  return transaction(async (client) => {
    const amount = await calculateEarnings(classId, teacherId);
    const platformFee = 0.00; // No platform fees per requirements
    
    const result = await client.query(
      `INSERT INTO earnings (class_id, teacher_id, amount, platform_fee, payout_status)
       VALUES ($1, $2, $3, $4, $5)
       RETURNING id`,
      [classId, teacherId, amount, platformFee, 'pending']
    );
    
    const earningsId = result.rows[0].id;
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        studentId, // Actor is the student who booked (or could be system)
        'earnings_created',
        'earnings',
        earningsId,
        JSON.stringify({ class_id: classId, amount, teacher_id: teacherId }),
      ]
    );
    
    return earningsId;
  });
}

/**
 * Get teacher's earnings (teacher can only see their own)
 */
export async function getTeacherEarnings(
  teacherId: number,
  startDate?: Date,
  endDate?: Date
): Promise<Earnings[]> {
  let queryText = `
    SELECT e.* FROM earnings e
    JOIN classes c ON e.class_id = c.id
    WHERE e.teacher_id = $1
  `;
  
  const params: any[] = [teacherId];
  
  if (startDate && endDate) {
    queryText += ` AND c.start_at_utc BETWEEN $2 AND $3`;
    params.push(startDate, endDate);
  }
  
  queryText += ` ORDER BY e.created_at DESC`;
  
  const result = await query(queryText, params);
  
  return result.rows.map(row => ({
    ...row,
    paid_at: row.paid_at ? new Date(row.paid_at) : null,
    created_at: new Date(row.created_at),
  }));
}

/**
 * Get all earnings (admin only)
 */
export async function getAllEarnings(
  startDate?: Date,
  endDate?: Date,
  teacherId?: number
): Promise<Earnings[]> {
  let queryText = `
    SELECT e.* FROM earnings e
    JOIN classes c ON e.class_id = c.id
    WHERE 1=1
  `;
  
  const params: any[] = [];
  let paramCount = 0;
  
  if (teacherId) {
    paramCount++;
    queryText += ` AND e.teacher_id = $${paramCount}`;
    params.push(teacherId);
  }
  
  if (startDate && endDate) {
    paramCount++;
    queryText += ` AND c.start_at_utc BETWEEN $${paramCount} AND $${paramCount + 1}`;
    params.push(startDate, endDate);
    paramCount++;
  }
  
  queryText += ` ORDER BY e.created_at DESC`;
  
  const result = await query(queryText, params);
  
  return result.rows.map(row => ({
    ...row,
    paid_at: row.paid_at ? new Date(row.paid_at) : null,
    created_at: new Date(row.created_at),
  }));
}

/**
 * Mark earnings as paid (admin only)
 */
export async function markEarningsPaid(
  earningsId: string,
  adminId: number
): Promise<void> {
  await transaction(async (client) => {
    await client.query(
      `UPDATE earnings 
       SET payout_status = 'paid', paid_at = NOW()
       WHERE id = $1`,
      [earningsId]
    );
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        adminId,
        'earnings_marked_paid',
        'earnings',
        earningsId,
        JSON.stringify({ paid_at: new Date().toISOString() }),
      ]
    );
  });
}

/**
 * Get earnings summary for teacher
 */
export async function getTeacherEarningsSummary(
  teacherId: number,
  startDate?: Date,
  endDate?: Date
): Promise<{
  total: number;
  pending: number;
  paid: number;
  count: number;
}> {
  let queryText = `
    SELECT 
      COALESCE(SUM(e.amount), 0) as total,
      COALESCE(SUM(CASE WHEN e.payout_status = 'pending' THEN e.amount ELSE 0 END), 0) as pending,
      COALESCE(SUM(CASE WHEN e.payout_status = 'paid' THEN e.amount ELSE 0 END), 0) as paid,
      COUNT(*) as count
    FROM earnings e
    JOIN classes c ON e.class_id = c.id
    WHERE e.teacher_id = $1
  `;
  
  const params: any[] = [teacherId];
  
  if (startDate && endDate) {
    queryText += ` AND c.start_at_utc BETWEEN $2 AND $3`;
    params.push(startDate, endDate);
  }
  
  const result = await query(queryText, params);
  
  return {
    total: parseFloat(result.rows[0].total) || 0,
    pending: parseFloat(result.rows[0].pending) || 0,
    paid: parseFloat(result.rows[0].paid) || 0,
    count: parseInt(result.rows[0].count) || 0,
  };
}







