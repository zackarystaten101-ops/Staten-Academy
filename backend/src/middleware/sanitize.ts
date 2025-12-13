/**
 * Response Sanitization Middleware
 * Removes sensitive data (teacher earnings, pay rates) from student-facing responses
 */

import { Response, NextFunction } from 'express';
import { AuthRequest } from './auth.js';

/**
 * Sanitize response data based on user role
 * CRITICAL: Students must NEVER see teacher earnings or pay rates
 */
export function sanitizeResponse(req: AuthRequest, res: Response, next: NextFunction) {
  const originalJson = res.json.bind(res);
  
  res.json = function (data: any) {
    const userRole = req.userRole;
    
    // If student, strip all earnings-related fields
    if (userRole === 'student' || userRole === 'new_student') {
      data = sanitizeForStudent(data);
    }
    
    return originalJson(data);
  };
  
  next();
}

/**
 * Recursively remove sensitive fields from data
 */
function sanitizeForStudent(data: any): any {
  if (Array.isArray(data)) {
    return data.map(item => sanitizeForStudent(item));
  }
  
  if (data && typeof data === 'object') {
    const sanitized: any = {};
    
    for (const [key, value] of Object.entries(data)) {
      // Remove earnings-related fields
      if (
        key === 'earnings' ||
        key === 'earnings_amount' ||
        key === 'teacher_rate' ||
        key === 'teacher_share' ||
        key === 'pay' ||
        key === 'payout_amount' ||
        key === 'default_rate' ||
        key === 'group_class_rate' ||
        key === 'bonus_rate' ||
        key === 'platform_fee' ||
        key === 'net_amount' ||
        key === 'payout_status' ||
        key === 'payout_info'
      ) {
        // Skip this field
        continue;
      }
      
      // Recursively sanitize nested objects
      if (value && typeof value === 'object') {
        sanitized[key] = sanitizeForStudent(value);
      } else {
        sanitized[key] = value;
      }
    }
    
    return sanitized;
  }
  
  return data;
}


