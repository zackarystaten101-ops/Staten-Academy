/**
 * Calendar Service
 * Unified calendar combining classes, availability, time-off, slot requests
 * CRITICAL: Sanitizes earnings data for students
 */

import { query } from '../config/database.js';
import { formatInUserTimezone } from '../utils/timezone.js';

export interface CalendarEvent {
  id: string;
  type: 'class' | 'availability' | 'slot_request' | 'time_off';
  title: string;
  start: Date;
  end: Date;
  color: string;
  status?: string;
  teacher_id?: number;
  student_id?: number;
  teacher_name?: string;
  student_name?: string;
  // Earnings data - should be stripped for students
  earnings_amount?: number;
  teacher_rate?: number;
}

const COLOR_MAP = {
  confirmed: '#28a745', // Green
  recurring: '#0b6cf5', // Blue
  pending: '#ffc107', // Orange
  requested: '#ffc107', // Orange
  time_off: '#6c757d', // Gray
  group: '#6f42c1', // Purple
  video_session: '#20c997', // Teal
  cancelled: '#dc3545', // Red (muted/strikethrough)
};

/**
 * Get unified calendar events for a user
 */
export async function getUnifiedCalendar(
  userId: number,
  role: 'student' | 'teacher' | 'admin',
  startDate: Date,
  endDate: Date,
  userTimezone: string = 'UTC'
): Promise<CalendarEvent[]> {
  const events: CalendarEvent[] = [];
  
  // Get classes
  let classesQuery = '';
  if (role === 'teacher') {
    classesQuery = `
      SELECT 
        c.id,
        c.type,
        c.title,
        c.start_at_utc,
        c.end_at_utc,
        c.status,
        c.recurrence_group_id,
        c.teacher_id,
        c.student_id,
        u.name as student_name,
        e.amount as earnings_amount
      FROM classes c
      LEFT JOIN users u ON c.student_id = u.id
      LEFT JOIN earnings e ON c.earnings_record_id = e.id
      WHERE c.teacher_id = $1
      AND c.start_at_utc BETWEEN $2 AND $3
    `;
  } else if (role === 'student') {
    classesQuery = `
      SELECT 
        c.id,
        c.type,
        c.title,
        c.start_at_utc,
        c.end_at_utc,
        c.status,
        c.recurrence_group_id,
        c.teacher_id,
        c.student_id,
        u.name as teacher_name
      FROM classes c
      LEFT JOIN users u ON c.teacher_id = u.id
      WHERE c.student_id = $1
      AND c.start_at_utc BETWEEN $2 AND $3
    `;
  } else {
    // Admin: see all classes
    classesQuery = `
      SELECT 
        c.id,
        c.type,
        c.title,
        c.start_at_utc,
        c.end_at_utc,
        c.status,
        c.recurrence_group_id,
        c.teacher_id,
        c.student_id,
        u1.name as teacher_name,
        u2.name as student_name,
        e.amount as earnings_amount
      FROM classes c
      LEFT JOIN users u1 ON c.teacher_id = u1.id
      LEFT JOIN users u2 ON c.student_id = u2.id
      LEFT JOIN earnings e ON c.earnings_record_id = e.id
      WHERE c.start_at_utc BETWEEN $1 AND $2
    `;
  }
  
  classesQuery += ` ORDER BY c.start_at_utc`;
  
  const classesParams = role === 'admin' 
    ? [startDate, endDate]
    : [userId, startDate, endDate];
  
  const classesResult = await query(classesQuery, classesParams);
  
  for (const row of classesResult.rows) {
    const status = row.status;
    let color = COLOR_MAP[status as keyof typeof COLOR_MAP] || COLOR_MAP.confirmed;
    
    // Special handling for recurring
    if (row.recurrence_group_id) {
      color = COLOR_MAP.recurring;
    }
    
    // Special handling for type
    if (row.type === 'group') {
      color = COLOR_MAP.group;
    } else if (row.type === 'video_session') {
      color = COLOR_MAP.video_session;
    }
    
    const event: CalendarEvent = {
      id: row.id,
      type: 'class',
      title: row.title || `${row.type} Lesson`,
      start: new Date(row.start_at_utc),
      end: new Date(row.end_at_utc),
      color,
      status: row.status,
      teacher_id: row.teacher_id,
      student_id: row.student_id,
      teacher_name: row.teacher_name,
      student_name: row.student_name,
    };
    
    // Only include earnings for teachers/admins (NOT students)
    if (role !== 'student' && row.earnings_amount) {
      event.earnings_amount = parseFloat(row.earnings_amount);
    }
    
    events.push(event);
  }
  
  // Get availability slots (for teachers/admins)
  if (role === 'teacher' || role === 'admin') {
    let availabilityQuery = '';
    if (role === 'teacher') {
      availabilityQuery = `
        SELECT 
          id,
          start_at_utc,
          end_at_utc,
          is_open
        FROM availability_slots
        WHERE teacher_id = $1
        AND start_at_utc BETWEEN $2 AND $3
        AND is_open = TRUE
      `;
    } else {
      availabilityQuery = `
        SELECT 
          a.id,
          a.start_at_utc,
          a.end_at_utc,
          a.is_open,
          u.name as teacher_name
        FROM availability_slots a
        JOIN users u ON a.teacher_id = u.id
        WHERE a.start_at_utc BETWEEN $1 AND $2
        AND a.is_open = TRUE
      `;
    }
    
    const availabilityParams = role === 'teacher'
      ? [userId, startDate, endDate]
      : [startDate, endDate];
    
    const availabilityResult = await query(availabilityQuery, availabilityParams);
    
    for (const row of availabilityResult.rows) {
      events.push({
        id: row.id,
        type: 'availability',
        title: 'Available',
        start: new Date(row.start_at_utc),
        end: new Date(row.end_at_utc),
        color: '#e9ecef', // Light gray for availability
        teacher_id: role === 'admin' ? undefined : userId,
        teacher_name: row.teacher_name,
      });
    }
  }
  
  // Get pending slot requests (for teachers)
  if (role === 'teacher') {
    const requestsResult = await query(
      `SELECT 
        sr.id,
        sr.requested_start_utc,
        sr.requested_end_utc,
        sr.status,
        u.name as student_name
      FROM slot_requests sr
      JOIN users u ON sr.student_id = u.id
      WHERE sr.teacher_id = $1
      AND sr.status = 'pending'
      AND sr.requested_start_utc BETWEEN $2 AND $3
      ORDER BY sr.requested_start_utc`,
      [userId, startDate, endDate]
    );
    
    for (const row of requestsResult.rows) {
      events.push({
        id: row.id,
        type: 'slot_request',
        title: `Booking Request: ${row.student_name || 'Student'}`,
        start: new Date(row.requested_start_utc),
        end: new Date(row.requested_end_utc),
        color: COLOR_MAP.pending,
        status: row.status,
        student_name: row.student_name,
      });
    }
  }
  
  // Sort by start time
  events.sort((a, b) => a.start.getTime() - b.start.getTime());
  
  return events;
}

/**
 * Sanitize calendar events for role (remove sensitive data for students)
 * This is an additional layer of protection (middleware also does this)
 */
export function sanitizeForRole(
  events: CalendarEvent[],
  role: 'student' | 'teacher' | 'admin'
): CalendarEvent[] {
  if (role === 'student') {
    return events.map(event => {
      const sanitized = { ...event };
      delete sanitized.earnings_amount;
      delete sanitized.teacher_rate;
      return sanitized;
    });
  }
  
  return events;
}

/**
 * Color code events (apply color scheme)
 */
export function colorCodeEvents(events: CalendarEvent[]): CalendarEvent[] {
  return events.map(event => {
    // Color is already set in getUnifiedCalendar, but this can be used for additional styling
    return event;
  });
}

