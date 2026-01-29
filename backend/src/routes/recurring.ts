/**
 * Recurring Booking Routes
 */

import express from 'express';
import { requireAuth, requireRole, AuthRequest } from '../middleware/auth.js';
import { sanitizeResponse } from '../middleware/sanitize.js';
import {
  createRecurringSeries,
  generateFutureClassesForSeries,
  handlePaymentFailure,
  resetPaymentFailures,
  pauseRecurringSeries,
  resumeRecurringSeries,
} from '../services/RecurringBookingService.js';

const router = express.Router();

router.use(requireAuth);
router.use(sanitizeResponse);

/**
 * POST /api/recurring
 * Create a recurring weekly booking series
 */
router.post('/', requireRole(['student', 'admin']), async (req: AuthRequest, res) => {
  try {
    const { teacherId, dayOfWeek, startTime, endTime, startDate, endDate, entitlementType } = req.body;
    
    if (!teacherId || dayOfWeek === undefined || !startTime || !endTime || !startDate) {
      return res.status(400).json({ error: 'Missing required fields' });
    }
    
    const studentId = req.userRole === 'admin' && req.body.studentId
      ? parseInt(req.body.studentId)
      : req.userId!;
    
    const { recurrenceGroupId, classIds } = await createRecurringSeries(
      studentId,
      teacherId,
      dayOfWeek,
      startTime,
      endTime,
      new Date(startDate),
      endDate ? new Date(endDate) : null,
      entitlementType || 'one_on_one_class'
    );
    
    res.json({
      success: true,
      recurrenceGroupId,
      classIds,
      message: `Created ${classIds.length} recurring classes`,
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/recurring/:id/generate
 * Generate future classes for a series
 */
router.post('/:id/generate', requireRole(['student', 'teacher', 'admin']), async (req: AuthRequest, res) => {
  try {
    const recurrenceGroupId = req.params.id;
    const weeksAhead = parseInt(req.body.weeksAhead) || 4;
    
    const classIds = await generateFutureClassesForSeries(recurrenceGroupId, weeksAhead);
    
    res.json({
      success: true,
      classIds,
      message: `Generated ${classIds.length} future classes`,
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/recurring/:id/payment-failure
 * Record payment failure (system/admin only)
 */
router.post('/:id/payment-failure', requireRole(['admin']), async (req: AuthRequest, res) => {
  try {
    const recurrenceGroupId = req.params.id;
    
    const { cancelled, paymentFailures } = await handlePaymentFailure(recurrenceGroupId);
    
    res.json({
      success: true,
      cancelled,
      paymentFailures,
      message: cancelled 
        ? 'Series cancelled due to payment failures'
        : `Payment failure recorded (${paymentFailures}/2)`,
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/recurring/:id/reset-payment
 * Reset payment failures (when payment succeeds)
 */
router.post('/:id/reset-payment', requireRole(['admin']), async (req: AuthRequest, res) => {
  try {
    const recurrenceGroupId = req.params.id;
    
    await resetPaymentFailures(recurrenceGroupId);
    
    res.json({
      success: true,
      message: 'Payment failures reset',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/recurring/:id/pause
 * Pause recurring series
 */
router.post('/:id/pause', requireRole(['student', 'teacher', 'admin']), async (req: AuthRequest, res) => {
  try {
    const recurrenceGroupId = req.params.id;
    
    await pauseRecurringSeries(recurrenceGroupId);
    
    res.json({
      success: true,
      message: 'Recurring series paused',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

/**
 * POST /api/recurring/:id/resume
 * Resume recurring series
 */
router.post('/:id/resume', requireRole(['student', 'teacher', 'admin']), async (req: AuthRequest, res) => {
  try {
    const recurrenceGroupId = req.params.id;
    
    await resumeRecurringSeries(recurrenceGroupId);
    
    res.json({
      success: true,
      message: 'Recurring series resumed',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

export default router;








