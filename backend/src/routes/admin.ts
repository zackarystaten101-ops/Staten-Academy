/**
 * Admin Routes
 * Admin-only endpoints for managing teachers, plans, bookings, payroll
 */

import express from 'express';
import { requireAuth, requireRole, AuthRequest } from '../middleware/auth.js';
import { sanitizeResponse } from '../middleware/sanitize.js';
import { query, transaction } from '../config/database.js';
import { stringify } from 'csv-stringify';

const router = express.Router();

// All routes require admin role
router.use(requireAuth);
router.use(requireRole(['admin']));
router.use(sanitizeResponse);

/**
 * POST /api/admin/slots/add
 * Admin adds availability slots for a teacher
 */
router.post('/slots/add', async (req: AuthRequest, res) => {
  try {
    const { teacherId, startUtc, endUtc, source = 'admin', meta = {} } = req.body;
    
    if (!teacherId || !startUtc || !endUtc) {
      return res.status(400).json({ error: 'Missing required fields' });
    }
    
    const result = await query(
      `INSERT INTO availability_slots 
       (teacher_id, start_at_utc, end_at_utc, is_open, source, meta)
       VALUES ($1, $2, $3, $4, $5, $6)
       RETURNING id`,
      [teacherId, startUtc, endUtc, true, source, JSON.stringify(meta)]
    );
    
    // Audit log
    await query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        req.userId,
        'availability_slot_added',
        'availability_slot',
        result.rows[0].id,
        JSON.stringify({ teacher_id: teacherId, start: startUtc, end: endUtc }),
      ]
    );
    
    res.json({
      success: true,
      slotId: result.rows[0].id,
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/admin/bookings/force
 * Admin force-books a class
 */
router.post('/bookings/force', async (req: AuthRequest, res) => {
  try {
    const { teacherId, studentId, startUtc, endUtc, type = 'one_on_one' } = req.body;
    
    if (!teacherId || !studentId || !startUtc || !endUtc) {
      return res.status(400).json({ error: 'Missing required fields' });
    }
    
    // Force booking - bypasses normal flow
    const result = await query(
      `INSERT INTO classes 
       (type, title, start_at_utc, end_at_utc, status, teacher_id, student_id, meta)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
       RETURNING id`,
      [
        type,
        'Admin Booked Class',
        startUtc,
        endUtc,
        'confirmed',
        teacherId,
        studentId,
        JSON.stringify({ force_booked_by: req.userId }),
      ]
    );
    
    const classId = result.rows[0].id;
    
    // Create earnings record
    const { createEarningsRecord } = await import('../services/EarningsService.js');
    const earningsId = await createEarningsRecord(classId, teacherId, studentId);
    
    await query(
      `UPDATE classes SET earnings_record_id = $1 WHERE id = $2`,
      [earningsId, classId]
    );
    
    // Audit log
    await query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        req.userId,
        'class_force_booked',
        'class',
        classId,
        JSON.stringify({ teacher_id: teacherId, student_id: studentId }),
      ]
    );
    
    res.json({
      success: true,
      classId,
      earningsId,
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * PATCH /api/admin/teachers/:id/rate
 * Set teacher pay rate
 */
router.patch('/teachers/:id/rate', async (req: AuthRequest, res) => {
  try {
    const teacherId = parseInt(req.params.id);
    const { defaultRate, groupClassRate, bonusRate } = req.body;
    
    // Upsert teacher profile
    await query(
      `INSERT INTO teacher_profiles (teacher_id, default_rate, group_class_rate, bonus_rate)
       VALUES ($1, $2, $3, $4)
       ON CONFLICT (teacher_id)
       DO UPDATE SET 
         default_rate = EXCLUDED.default_rate,
         group_class_rate = EXCLUDED.group_class_rate,
         bonus_rate = EXCLUDED.bonus_rate,
         updated_at = NOW()`,
      [
        teacherId,
        defaultRate || 15.00,
        groupClassRate || null,
        bonusRate || null,
      ]
    );
    
    // Audit log
    await query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        req.userId,
        'teacher_rate_updated',
        'teacher_profile',
        teacherId.toString(),
        JSON.stringify({ default_rate: defaultRate, group_class_rate: groupClassRate }),
      ]
    );
    
    res.json({
      success: true,
      message: 'Teacher rate updated',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * GET /api/admin/audit-logs
 * Get audit logs
 */
router.get('/audit-logs', async (req: AuthRequest, res) => {
  try {
    const { startDate, endDate, action, targetType, actorId } = req.query;
    const limit = parseInt(req.query.limit as string) || 100;
    
    let queryText = `SELECT * FROM audit_logs WHERE 1=1`;
    const params: any[] = [];
    let paramCount = 0;
    
    if (startDate) {
      paramCount++;
      queryText += ` AND created_at >= $${paramCount}`;
      params.push(startDate);
    }
    
    if (endDate) {
      paramCount++;
      queryText += ` AND created_at <= $${paramCount}`;
      params.push(endDate);
    }
    
    if (action) {
      paramCount++;
      queryText += ` AND action = $${paramCount}`;
      params.push(action);
    }
    
    if (targetType) {
      paramCount++;
      queryText += ` AND target_type = $${paramCount}`;
      params.push(targetType);
    }
    
    if (actorId) {
      paramCount++;
      queryText += ` AND actor_id = $${paramCount}`;
      params.push(parseInt(actorId as string));
    }
    
    queryText += ` ORDER BY created_at DESC LIMIT $${paramCount + 1}`;
    params.push(limit);
    
    const result = await query(queryText, params);
    
    res.json({
      success: true,
      logs: result.rows.map(row => ({
        ...row,
        created_at: new Date(row.created_at),
        changes: row.changes || {},
      })),
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * POST /api/admin/payroll/export
 * Export payroll as CSV
 */
router.post('/payroll/export', async (req: AuthRequest, res) => {
  try {
    const { startDate, endDate, teacherId } = req.body;
    
    const { getAllEarnings } = await import('../services/EarningsService.js');
    const earnings = await getAllEarnings(
      startDate ? new Date(startDate) : undefined,
      endDate ? new Date(endDate) : undefined,
      teacherId
    );
    
    // Get teacher names
    const earningsWithNames = await Promise.all(
      earnings.map(async (earning) => {
        const teacherResult = await query(
          `SELECT name, email FROM users WHERE id = $1`,
          [earning.teacher_id]
        );
        
        const classResult = await query(
          `SELECT start_at_utc, end_at_utc FROM classes WHERE id = $1`,
          [earning.class_id]
        );
        
        return {
          ...earning,
          teacher_name: teacherResult.rows[0]?.name || 'Unknown',
          teacher_email: teacherResult.rows[0]?.email || 'Unknown',
          class_date: classResult.rows[0]?.start_at_utc || null,
        };
      })
    );
    
    // Convert to CSV
    const csvData = earningsWithNames.map(e => ({
      'Teacher Name': e.teacher_name,
      'Teacher Email': e.teacher_email,
      'Class Date': e.class_date ? new Date(e.class_date).toISOString() : '',
      'Amount': e.amount,
      'Platform Fee': e.platform_fee,
      'Payout Status': e.payout_status,
      'Paid At': e.paid_at ? new Date(e.paid_at).toISOString() : '',
    }));
    
    // Convert to CSV using callback-style stringify
    const csv = await new Promise<string>((resolve, reject) => {
      stringify(csvData, { header: true }, (err: Error | undefined, output: string) => {
        if (err) reject(err);
        else resolve(output || '');
      });
    });
    
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', `attachment; filename="payroll-${new Date().toISOString()}.csv"`);
    res.send(csv);
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

export default router;

