/**
 * Timezone utilities
 * Store all times in UTC, display in user's timezone
 */

import { formatInTimeZone, toZonedTime, fromZonedTime } from 'date-fns-tz';
import { addMinutes, differenceInMinutes } from 'date-fns';

/**
 * Convert UTC date to user's timezone for display
 */
export function toUserTimezone(utcDate: Date, userTimezone: string = 'UTC'): Date {
  return toZonedTime(utcDate, userTimezone);
}

/**
 * Convert user's local time to UTC for storage
 */
export function toUTC(localDate: Date, userTimezone: string = 'UTC'): Date {
  return fromZonedTime(localDate, userTimezone);
}

/**
 * Format date in user's timezone
 */
export function formatInUserTimezone(
  utcDate: Date,
  format: string,
  userTimezone: string = 'UTC'
): string {
  return formatInTimeZone(utcDate, userTimezone, format);
}

/**
 * Get timezone offset in minutes
 */
export function getTimezoneOffset(timezone: string): number {
  const now = new Date();
  const utc = new Date(now.toLocaleString('en-US', { timeZone: 'UTC' }));
  const tz = new Date(now.toLocaleString('en-US', { timeZone: timezone }));
  return differenceInMinutes(utc, tz);
}

/**
 * Ensure date is in UTC (if it's a string, parse it as UTC)
 */
export function ensureUTC(date: Date | string): Date {
  if (typeof date === 'string') {
    // If string doesn't have timezone info, treat as UTC
    if (!date.includes('Z') && !date.includes('+') && !date.includes('-', 10)) {
      return new Date(date + 'Z');
    }
    return new Date(date);
  }
  return date;
}

