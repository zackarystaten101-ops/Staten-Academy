/**
 * Calendar Routes
 * Unified calendar endpoints
 * CRITICAL: Sanitizes earnings data for students
 */

import express from 'express';
import { requireAuth, optionalAuth, AuthRequest } from '../middleware/auth.js';
import { sanitizeResponse } from '../middleware/sanitize.js';
import { getUnifiedCalendar, sanitizeForRole } from '../services/CalendarService.js';

const router = express.Router();

// All routes require authentication
router.use(requireAuth);
router.use(sanitizeResponse);

/**
 * GET /api/calendar
 * Get unified calendar events
 * Query params: view (day/week/month), startDate, endDate, userId
 */
router.get('/', async (req: AuthRequest, res) => {
  try {
    const { view, startDate, endDate, userId } = req.query;
    
    // Determine target user
    let targetUserId = req.userId!;
    let userRole = req.userRole!;
    
    // Admins can view any user's calendar
    if (userRole === 'admin' && userId) {
      targetUserId = parseInt(userId as string);
    }
    
    // Parse dates
    const start = startDate 
      ? new Date(startDate as string)
      : new Date();
    
    const end = endDate
      ? new Date(endDate as string)
      : new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000); // Default 7 days
    
    // Get calendar events
    const events = await getUnifiedCalendar(
      targetUserId,
      userRole as 'student' | 'teacher' | 'admin',
      start,
      end
    );
    
    // Additional sanitization (redundant but safe)
    const sanitizedEvents = sanitizeForRole(events, userRole as 'student' | 'teacher' | 'admin');
    
    res.json({
      success: true,
      events: sanitizedEvents,
      view: view || 'week',
      startDate: start.toISOString(),
      endDate: end.toISOString(),
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * GET /api/calendar/availability/:teacherId
 * Get teacher availability slots
 */
router.get('/availability/:teacherId', async (req: AuthRequest, res) => {
  try {
    const teacherId = parseInt(req.params.teacherId);
    const { startDate, endDate } = req.query;
    
    const start = startDate ? new Date(startDate as string) : new Date();
    const end = endDate 
      ? new Date(endDate as string)
      : new Date(start.getTime() + 30 * 24 * 60 * 60 * 1000); // Default 30 days
    
    const { query } = await import('../config/database.js');
    
    const result = await query(
      `SELECT * FROM availability_slots
       WHERE teacher_id = $1
       AND start_at_utc BETWEEN $2 AND $3
       AND is_open = TRUE
       ORDER BY start_at_utc`,
      [teacherId, start, end]
    );
    
    res.json({
      success: true,
      slots: result.rows.map(row => ({
        ...row,
        start_at_utc: new Date(row.start_at_utc),
        end_at_utc: new Date(row.end_at_utc),
      })),
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

export default router;

