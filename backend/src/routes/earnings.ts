/**
 * Earnings Routes
 * Teacher earnings endpoints (NOT visible to students)
 * CRITICAL: Students must never access these endpoints
 */

import express from 'express';
import { requireAuth, requireRole, AuthRequest } from '../middleware/auth.js';
import { sanitizeResponse } from '../middleware/sanitize.js';
import {
  getTeacherEarnings,
  getTeacherEarningsSummary,
  markEarningsPaid,
} from '../services/EarningsService.js';

const router = express.Router();

// All routes require authentication - STUDENTS ARE BLOCKED
router.use(requireAuth);
router.use(requireRole(['teacher', 'admin'])); // Explicitly block students
router.use(sanitizeResponse);

/**
 * GET /api/earnings
 * Get teacher's own earnings (or all earnings if admin)
 */
router.get('/', async (req: AuthRequest, res) => {
  try {
    const { startDate, endDate, teacherId } = req.query;
    
    const start = startDate ? new Date(startDate as string) : undefined;
    const end = endDate ? new Date(endDate as string) : undefined;
    
    let earnings;
    
    if (req.userRole === 'admin') {
      // Admin can see all earnings or filter by teacher
      const { getAllEarnings } = await import('../services/EarningsService.js');
      const targetTeacherId = teacherId ? parseInt(teacherId as string) : undefined;
      earnings = await getAllEarnings(start, end, targetTeacherId);
    } else {
      // Teacher can only see their own
      earnings = await getTeacherEarnings(req.userId!, start, end);
    }
    
    res.json({
      success: true,
      earnings,
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * GET /api/earnings/summary
 * Get earnings summary (totals, pending, paid)
 */
router.get('/summary', async (req: AuthRequest, res) => {
  try {
    const { startDate, endDate } = req.query;
    
    const start = startDate ? new Date(startDate as string) : undefined;
    const end = endDate ? new Date(endDate as string) : undefined;
    
    // Teachers can only see their own summary
    const summary = await getTeacherEarningsSummary(req.userId!, start, end);
    
    res.json({
      success: true,
      summary,
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * POST /api/earnings/:id/mark-paid
 * Mark earnings as paid (admin only)
 */
router.post('/:id/mark-paid', requireRole(['admin']), async (req: AuthRequest, res) => {
  try {
    const earningsId = req.params.id;
    
    await markEarningsPaid(earningsId, req.userId!);
    
    res.json({
      success: true,
      message: 'Earnings marked as paid',
    });
  } catch (error: any) {
    res.status(400).json({ error: error.message });
  }
});

export default router;



