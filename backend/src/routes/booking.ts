/**
 * Booking Routes
 * Handles slot requests, class bookings, cancellations
 */

import express from 'express';
import { requireAuth, requireRole, AuthRequest } from '../middleware/auth.js';
import { sanitizeResponse } from '../middleware/sanitize.js';
import {
  requestSlot,
  acceptSlotRequest,
  declineSlotRequest,
  cancelClass,
  completeClass,
  markNoShow,
} from '../services/BookingService.js';
import { EntitlementType } from '../services/WalletService.js';

const router = express.Router();

// All routes require authentication
router.use(requireAuth);
router.use(sanitizeResponse);

/**
 * POST /api/bookings/slots/request
 * Student requests a slot (creates hold on entitlement)
 */
router.post('/slots/request', requireRole(['student', 'admin']), async (req: AuthRequest, res) => {
  try {
    const { teacherId, startUtc, endUtc, entitlementType } = req.body;
    
    if (!teacherId || !startUtc || !endUtc) {
      return res.status(400).json({ error: 'Missing required fields' });
    }
    
    // Students can only request for themselves
    const studentId = req.userRole === 'admin' && req.body.studentId 
      ? parseInt(req.body.studentId)
      : req.userId!;
    
    const { slotRequestId, entitlementHoldId } = await requestSlot(
      studentId,
      teacherId,
      startUtc,
      endUtc,
      entitlementType || 'one_on_one_class'
    );
    
    res.json({
      success: true,
      slotRequestId,
      entitlementHoldId,
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/bookings/slots/:id/accept
 * Teacher accepts a slot request
 */
router.post('/slots/:id/accept', requireRole(['teacher', 'admin']), async (req: AuthRequest, res) => {
  try {
    const requestId = req.params.id;
    const teacherId = req.userRole === 'admin' && req.body.teacherId
      ? parseInt(req.body.teacherId)
      : req.userId!;
    
    const { classId, earningsId } = await acceptSlotRequest(requestId, teacherId);
    
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
 * POST /api/bookings/slots/:id/decline
 * Teacher declines a slot request
 */
router.post('/slots/:id/decline', requireRole(['teacher', 'admin']), async (req: AuthRequest, res) => {
  try {
    const requestId = req.params.id;
    const { reason } = req.body;
    const teacherId = req.userRole === 'admin' && req.body.teacherId
      ? parseInt(req.body.teacherId)
      : req.userId!;
    
    await declineSlotRequest(requestId, teacherId, reason);
    
    res.json({
      success: true,
      message: 'Slot request declined',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * GET /api/bookings/classes
 * Get classes for current user (role-based)
 */
router.get('/classes', async (req: AuthRequest, res) => {
  try {
    const { startDate, endDate } = req.query;
    const { query } = await import('../config/database.js');
    
    let queryText = '';
    const params: any[] = [];
    
    if (req.userRole === 'teacher') {
      queryText = `SELECT * FROM classes WHERE teacher_id = $1`;
      params.push(req.userId);
    } else if (req.userRole === 'student') {
      queryText = `SELECT * FROM classes WHERE student_id = $1`;
      params.push(req.userId);
    } else {
      // Admin sees all
      queryText = `SELECT * FROM classes WHERE 1=1`;
    }
    
    if (startDate) {
      const paramIndex = params.length + 1;
      queryText += ` AND start_at_utc >= $${paramIndex}`;
      params.push(startDate);
    }
    
    if (endDate) {
      const paramIndex = params.length + 1;
      queryText += ` AND start_at_utc <= $${paramIndex}`;
      params.push(endDate);
    }
    
    queryText += ` ORDER BY start_at_utc`;
    
    const result = await query(queryText, params);
    
    res.json({
      success: true,
      classes: result.rows.map(row => ({
        ...row,
        start_at_utc: new Date(row.start_at_utc),
        end_at_utc: new Date(row.end_at_utc),
        created_at: new Date(row.created_at),
        updated_at: new Date(row.updated_at),
        meta: row.meta || {},
      })),
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * POST /api/bookings/classes/:id/cancel
 * Cancel a class
 */
router.post('/classes/:id/cancel', async (req: AuthRequest, res) => {
  try {
    const classId = req.params.id;
    const { reason } = req.body;
    
    const cancelledByRole = req.userRole === 'admin' 
      ? 'admin'
      : req.userRole === 'teacher'
      ? 'teacher'
      : 'student';
    
    await cancelClass(classId, req.userId!, cancelledByRole, reason);
    
    res.json({
      success: true,
      message: 'Class cancelled',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/bookings/classes/:id/complete
 * Mark class as completed (teacher only)
 */
router.post('/classes/:id/complete', requireRole(['teacher', 'admin']), async (req: AuthRequest, res) => {
  try {
    const classId = req.params.id;
    const teacherId = req.userRole === 'admin' && req.body.teacherId
      ? parseInt(req.body.teacherId)
      : req.userId!;
    
    await completeClass(classId, teacherId);
    
    res.json({
      success: true,
      message: 'Class marked as completed',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/bookings/classes/:id/no-show
 * Mark no-show (student or teacher)
 */
router.post('/classes/:id/no-show', async (req: AuthRequest, res) => {
  try {
    const classId = req.params.id;
    const { noShowType } = req.body; // 'student' or 'teacher'
    
    if (!noShowType || !['student', 'teacher'].includes(noShowType)) {
      return res.status(400).json({ error: 'Invalid noShowType' });
    }
    
    const markedByRole = req.userRole === 'admin'
      ? 'admin'
      : req.userRole === 'teacher'
      ? 'teacher'
      : 'student';
    
    await markNoShow(classId, req.userId!, markedByRole, noShowType);
    
    res.json({
      success: true,
      message: 'No-show recorded',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

export default router;

