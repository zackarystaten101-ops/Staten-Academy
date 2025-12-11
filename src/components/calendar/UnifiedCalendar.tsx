/**
 * UnifiedCalendar Component
 * Preply-style unified calendar with day/week/month views and color-coding
 * CRITICAL: Never shows teacher earnings to students
 */

import React, { useState, useEffect } from 'react';
import { format, startOfWeek, endOfWeek, startOfMonth, endOfMonth, eachDayOfInterval, addWeeks, subWeeks, addMonths, subMonths, isSameDay, isToday, addDays, subDays } from 'date-fns';
import CalendarBlock from './CalendarBlock';
import BookingModal from './BookingModal';
import './UnifiedCalendar.css';

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
  // Earnings data - should NOT be present for students
  earnings_amount?: number;
  teacher_rate?: number;
}

export type CalendarView = 'day' | 'week' | 'month' | 'list';

interface UnifiedCalendarProps {
  userId: number;
  userRole: 'student' | 'teacher' | 'admin';
  apiBaseUrl?: string;
}

const COLOR_LEGEND = {
  confirmed: { color: '#28a745', label: 'Confirmed Lesson' },
  recurring: { color: '#0b6cf5', label: 'Recurring Lesson' },
  pending: { color: '#ffc107', label: 'Pending/Requested' },
  time_off: { color: '#6c757d', label: 'Time Off' },
  group: { color: '#6f42c1', label: 'Group Session' },
  video_session: { color: '#20c997', label: 'Video Session' },
  cancelled: { color: '#dc3545', label: 'Cancelled' },
};

const UnifiedCalendar: React.FC<UnifiedCalendarProps> = ({
  userId,
  userRole,
  apiBaseUrl = 'http://localhost:3001',
}) => {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [view, setView] = useState<CalendarView>('week');
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [loading, setLoading] = useState(false);

  // Fetch calendar events
  useEffect(() => {
    fetchEvents();
  }, [currentDate, view, userId]);

  const fetchEvents = async () => {
    setLoading(true);
    try {
      const startDate = getStartDateForView(currentDate, view);
      const endDate = getEndDateForView(currentDate, view);

      const response = await fetch(
        `${apiBaseUrl}/api/calendar?view=${view}&startDate=${startDate.toISOString()}&endDate=${endDate.toISOString()}&userId=${userId}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch calendar events');
      }

      const data = await response.json();
      
      // Verify no earnings data leaked to students
      if (userRole === 'student') {
        data.events = data.events.map((event: CalendarEvent) => {
          const sanitized = { ...event };
          delete sanitized.earnings_amount;
          delete sanitized.teacher_rate;
          return sanitized;
        });
      }
      
      setEvents(data.events.map((e: any) => ({
        ...e,
        start: new Date(e.start),
        end: new Date(e.end),
      })));
    } catch (error) {
      console.error('Error fetching calendar events:', error);
    } finally {
      setLoading(false);
    }
  };

  const getStartDateForView = (date: Date, view: CalendarView): Date => {
    switch (view) {
      case 'day':
        return date;
      case 'week':
        return startOfWeek(date, { weekStartsOn: 0 });
      case 'month':
        return startOfMonth(date);
      case 'list':
        return date;
      default:
        return date;
    }
  };

  const getEndDateForView = (date: Date, view: CalendarView): Date => {
    switch (view) {
      case 'day':
        return date;
      case 'week':
        return endOfWeek(date, { weekStartsOn: 0 });
      case 'month':
        return endOfMonth(date);
      case 'list':
        return new Date(date.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
      default:
        return date;
    }
  };

  const handleEventClick = (event: CalendarEvent) => {
    setSelectedEvent(event);
    setIsModalOpen(true);
  };

  const handlePrev = () => {
    switch (view) {
      case 'day':
        setCurrentDate(new Date(currentDate.getTime() - 24 * 60 * 60 * 1000));
        break;
      case 'week':
        setCurrentDate(subWeeks(currentDate, 1));
        break;
      case 'month':
        setCurrentDate(subMonths(currentDate, 1));
        break;
    }
  };

  const handleNext = () => {
    switch (view) {
      case 'day':
        setCurrentDate(new Date(currentDate.getTime() + 24 * 60 * 60 * 1000));
        break;
      case 'week':
        setCurrentDate(addWeeks(currentDate, 1));
        break;
      case 'month':
        setCurrentDate(addMonths(currentDate, 1));
        break;
    }
  };

  const handleToday = () => {
    setCurrentDate(new Date());
  };

  const renderDayView = () => {
    const dayEvents = events.filter(event => 
      isSameDay(event.start, currentDate)
    ).sort((a, b) => a.start.getTime() - b.start.getTime());

    return (
      <div className="calendar-day-view">
        <div className="calendar-day-header">
          <h2>{format(currentDate, 'EEEE, MMMM d, yyyy')}</h2>
        </div>
        <div className="calendar-day-events">
          {dayEvents.length === 0 ? (
            <div className="calendar-empty">No events for this day</div>
          ) : (
            dayEvents.map(event => (
              <CalendarBlock
                key={event.id}
                event={event}
                onClick={() => handleEventClick(event)}
                userRole={userRole}
              />
            ))
          )}
        </div>
      </div>
    );
  };

  const renderWeekView = () => {
    const weekStart = startOfWeek(currentDate, { weekStartsOn: 0 });
    const weekDays = eachDayOfInterval({
      start: weekStart,
      end: endOfWeek(currentDate, { weekStartsOn: 0 }),
    });

    const hours = Array.from({ length: 24 }, (_, i) => i);

    return (
      <div className="calendar-week-view">
        <div className="calendar-week-header">
          <div className="calendar-hour-column"></div>
          {weekDays.map(day => (
            <div key={day.toISOString()} className="calendar-day-header">
              <div className="calendar-day-name">{format(day, 'EEE')}</div>
              <div className={`calendar-day-number ${isToday(day) ? 'today' : ''}`}>
                {format(day, 'd')}
              </div>
            </div>
          ))}
        </div>
        <div className="calendar-week-body">
          <div className="calendar-hour-column">
            {hours.map(hour => (
              <div key={hour} className="calendar-hour">
                {format(new Date().setHours(hour, 0, 0, 0), 'h a')}
              </div>
            ))}
          </div>
          {weekDays.map(day => (
            <div key={day.toISOString()} className="calendar-day-column">
              {hours.map(hour => {
                const hourStart = new Date(day);
                hourStart.setHours(hour, 0, 0, 0);
                const hourEnd = new Date(day);
                hourEnd.setHours(hour + 1, 0, 0, 0);

                const hourEvents = events.filter(event =>
                  event.start >= hourStart && event.start < hourEnd && isSameDay(event.start, day)
                );

                return (
                  <div key={hour} className="calendar-hour-slot">
                    {hourEvents.map(event => (
                      <CalendarBlock
                        key={event.id}
                        event={event}
                        onClick={() => handleEventClick(event)}
                        userRole={userRole}
                        compact
                      />
                    ))}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    );
  };

  const renderMonthView = () => {
    const monthStart = startOfMonth(currentDate);
    const monthEnd = endOfMonth(currentDate);
    const monthDays = eachDayOfInterval({ start: monthStart, end: monthEnd });
    const weekStart = startOfWeek(monthStart, { weekStartsOn: 0 });
    const calendarDays = eachDayOfInterval({
      start: weekStart,
      end: endOfWeek(monthEnd, { weekStartsOn: 0 }),
    });

    return (
      <div className="calendar-month-view">
        <div className="calendar-month-grid">
          {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
            <div key={day} className="calendar-weekday-header">
              {day}
            </div>
          ))}
          {calendarDays.map(day => {
            const dayEvents = events.filter(event => isSameDay(event.start, day));
            const isCurrentMonth = day >= monthStart && day <= monthEnd;

            return (
              <div
                key={day.toISOString()}
                className={`calendar-month-day ${!isCurrentMonth ? 'other-month' : ''} ${isToday(day) ? 'today' : ''}`}
              >
                <div className="calendar-day-number">{format(day, 'd')}</div>
                <div className="calendar-day-events">
                  {dayEvents.slice(0, 3).map(event => (
                    <div
                      key={event.id}
                      className="calendar-event-dot"
                      style={{ backgroundColor: event.color }}
                      onClick={() => handleEventClick(event)}
                      title={event.title}
                    />
                  ))}
                  {dayEvents.length > 3 && (
                    <div className="calendar-more-events">+{dayEvents.length - 3}</div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    );
  };

  const renderListView = () => {
    const sortedEvents = [...events].sort((a, b) => a.start.getTime() - b.start.getTime());

    return (
      <div className="calendar-list-view">
        {sortedEvents.length === 0 ? (
          <div className="calendar-empty">No upcoming events</div>
        ) : (
          sortedEvents.map(event => (
            <CalendarBlock
              key={event.id}
              event={event}
              onClick={() => handleEventClick(event)}
              userRole={userRole}
              listView
            />
          ))
        )}
      </div>
    );
  };

  return (
    <div className="unified-calendar">
      {/* Header Controls */}
      <div className="calendar-header">
        <div className="calendar-controls">
          <button onClick={handlePrev} className="calendar-nav-btn">‹</button>
          <button onClick={handleToday} className="calendar-today-btn">Today</button>
          <button onClick={handleNext} className="calendar-nav-btn">›</button>
          <h2 className="calendar-title">
            {view === 'day' && format(currentDate, 'MMMM d, yyyy')}
            {view === 'week' && `Week of ${format(startOfWeek(currentDate, { weekStartsOn: 0 }), 'MMM d')}`}
            {view === 'month' && format(currentDate, 'MMMM yyyy')}
            {view === 'list' && 'Upcoming Events'}
          </h2>
        </div>
        <div className="calendar-view-switcher">
          {(['day', 'week', 'month', 'list'] as CalendarView[]).map(v => (
            <button
              key={v}
              className={`calendar-view-btn ${view === v ? 'active' : ''}`}
              onClick={() => setView(v)}
            >
              {v.charAt(0).toUpperCase() + v.slice(1)}
            </button>
          ))}
        </div>
      </div>

      {/* Color Legend */}
      <div className="calendar-legend">
        {Object.entries(COLOR_LEGEND).map(([key, { color, label }]) => (
          <div key={key} className="legend-item">
            <div className="legend-color" style={{ backgroundColor: color }}></div>
            <span className="legend-label">{label}</span>
          </div>
        ))}
      </div>

      {/* Calendar Content */}
      {loading ? (
        <div className="calendar-loading">Loading...</div>
      ) : (
        <>
          {view === 'day' && renderDayView()}
          {view === 'week' && renderWeekView()}
          {view === 'month' && renderMonthView()}
          {view === 'list' && renderListView()}
        </>
      )}

      {/* Booking Modal */}
      {isModalOpen && selectedEvent && (
        <BookingModal
          event={selectedEvent}
          userRole={userRole}
          onClose={() => setIsModalOpen(false)}
          apiBaseUrl={apiBaseUrl}
          onUpdate={fetchEvents}
        />
      )}
    </div>
  );
};

export default UnifiedCalendar;

