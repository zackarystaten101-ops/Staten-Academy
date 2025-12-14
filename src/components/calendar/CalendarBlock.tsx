/**
 * CalendarBlock Component
 * Individual event block in calendar
 * CRITICAL: Never shows earnings to students
 */

import React from 'react';
import { format } from 'date-fns';
import { CalendarEvent } from './UnifiedCalendar';
import './CalendarBlock.css';

interface CalendarBlockProps {
  event: CalendarEvent;
  onClick: () => void;
  userRole: 'student' | 'teacher' | 'admin';
  compact?: boolean;
  listView?: boolean;
}

const CalendarBlock: React.FC<CalendarBlockProps> = ({
  event,
  onClick,
  userRole,
  compact = false,
  listView = false,
}) => {
  const formatTime = (date: Date) => format(date, 'h:mm a');

  if (listView) {
    return (
      <div
        className="calendar-block-list"
        style={{ borderLeftColor: event.color }}
        onClick={onClick}
      >
        <div className="calendar-block-list-time">
          {formatTime(event.start)} - {formatTime(event.end)}
        </div>
        <div className="calendar-block-list-content">
          <div className="calendar-block-title">{event.title}</div>
          {event.teacher_name && (
            <div className="calendar-block-meta">Teacher: {event.teacher_name}</div>
          )}
          {event.student_name && userRole !== 'student' && (
            <div className="calendar-block-meta">Student: {event.student_name}</div>
          )}
          {event.status && (
            <span className={`calendar-block-status status-${event.status}`}>
              {event.status}
            </span>
          )}
        </div>
        {/* Earnings badge - ONLY for teachers/admins, NEVER for students */}
        {userRole !== 'student' && event.earnings_amount && (
          <div className="calendar-block-earnings">
            ${event.earnings_amount.toFixed(2)}
          </div>
        )}
      </div>
    );
  }

  if (compact) {
    return (
      <div
        className="calendar-block-compact"
        style={{ backgroundColor: event.color }}
        onClick={onClick}
        title={event.title}
      >
        <div className="calendar-block-compact-title">{event.title}</div>
        <div className="calendar-block-compact-time">
          {formatTime(event.start)}
        </div>
      </div>
    );
  }

  return (
    <div
      className="calendar-block"
      style={{ borderLeftColor: event.color }}
      onClick={onClick}
    >
      <div className="calendar-block-header">
        <div className="calendar-block-title">{event.title}</div>
        {event.status && (
          <span className={`calendar-block-status status-${event.status}`}>
            {event.status}
          </span>
        )}
      </div>
      <div className="calendar-block-time">
        {formatTime(event.start)} - {formatTime(event.end)}
      </div>
      {event.teacher_name && (
        <div className="calendar-block-meta">👤 {event.teacher_name}</div>
      )}
      {event.student_name && userRole !== 'student' && (
        <div className="calendar-block-meta">👤 {event.student_name}</div>
      )}
      {/* Earnings badge - ONLY for teachers/admins, NEVER for students */}
      {userRole !== 'student' && event.earnings_amount !== undefined && (
        <div className="calendar-block-earnings">
          💰 ${event.earnings_amount.toFixed(2)}
        </div>
      )}
    </div>
  );
};

export default CalendarBlock;



