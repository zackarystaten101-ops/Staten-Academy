/**
 * BookingModal Component
 * Shows class details and actions (reschedule, cancel, join)
 * CRITICAL: Never shows teacher earnings to students
 */

import React, { useState } from 'react';
import { format } from 'date-fns';
import { CalendarEvent } from './UnifiedCalendar';
import './BookingModal.css';

interface BookingModalProps {
  event: CalendarEvent;
  userRole: 'student' | 'teacher' | 'admin';
  onClose: () => void;
  apiBaseUrl?: string;
  onUpdate?: () => void;
}

const BookingModal: React.FC<BookingModalProps> = ({
  event,
  userRole,
  onClose,
  apiBaseUrl = 'http://localhost:3001',
  onUpdate,
}) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleCancel = async () => {
    if (!confirm('Are you sure you want to cancel this class?')) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        `${apiBaseUrl}/api/bookings/classes/${event.id}/cancel`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ reason: 'Cancelled by user' }),
        }
      );

      if (!response.ok) {
        throw new Error('Failed to cancel class');
      }

      if (onUpdate) {
        onUpdate();
      }
      onClose();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleJoin = () => {
    // Open classroom/video link
    // This would be handled by your existing classroom system
    window.location.href = `/classroom.php?class_id=${event.id}`;
  };

  return (
    <div className="booking-modal-overlay" onClick={onClose}>
      <div className="booking-modal" onClick={(e) => e.stopPropagation()}>
        <div className="booking-modal-header">
          <h2>{event.title}</h2>
          <button className="booking-modal-close" onClick={onClose}>×</button>
        </div>

        <div className="booking-modal-content">
          <div className="booking-modal-section">
            <div className="booking-modal-label">Time</div>
            <div className="booking-modal-value">
              {format(event.start, 'EEEE, MMMM d, yyyy')}<br />
              {format(event.start, 'h:mm a')} - {format(event.end, 'h:mm a')}
            </div>
          </div>

          {event.teacher_name && (
            <div className="booking-modal-section">
              <div className="booking-modal-label">Teacher</div>
              <div className="booking-modal-value">{event.teacher_name}</div>
            </div>
          )}

          {event.student_name && userRole !== 'student' && (
            <div className="booking-modal-section">
              <div className="booking-modal-label">Student</div>
              <div className="booking-modal-value">{event.student_name}</div>
            </div>
          )}

          {event.status && (
            <div className="booking-modal-section">
              <div className="booking-modal-label">Status</div>
              <div className={`booking-modal-value status-${event.status}`}>
                {event.status}
              </div>
            </div>
          )}

          {/* Earnings - ONLY for teachers/admins, NEVER for students */}
          {userRole !== 'student' && event.earnings_amount !== undefined && (
            <div className="booking-modal-section">
              <div className="booking-modal-label">Earnings</div>
              <div className="booking-modal-value earnings">
                ${event.earnings_amount.toFixed(2)}
              </div>
            </div>
          )}

          {error && (
            <div className="booking-modal-error">{error}</div>
          )}
        </div>

        <div className="booking-modal-actions">
          {event.status === 'confirmed' && (
            <button
              className="booking-modal-btn btn-primary"
              onClick={handleJoin}
            >
              Join Class
            </button>
          )}

          {event.status !== 'completed' && event.status !== 'cancelled' && (
            <button
              className="booking-modal-btn btn-danger"
              onClick={handleCancel}
              disabled={loading}
            >
              {loading ? 'Cancelling...' : 'Cancel Class'}
            </button>
          )}

          <button
            className="booking-modal-btn btn-secondary"
            onClick={onClose}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default BookingModal;


