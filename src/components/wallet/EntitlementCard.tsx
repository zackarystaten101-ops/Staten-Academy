/**
 * EntitlementCard Component
 * Displays individual entitlement (e.g., "3 one-on-one classes remaining")
 */

import React from 'react';
import { format } from 'date-fns';
import { Entitlement } from './WalletView';
import './EntitlementCard.css';

interface EntitlementCardProps {
  entitlement: Entitlement;
  label: string;
  icon: string;
}

const EntitlementCard: React.FC<EntitlementCardProps> = ({
  entitlement,
  label,
  icon,
}) => {
  const percentageRemaining =
    entitlement.quantity_total > 0
      ? (entitlement.quantity_remaining / entitlement.quantity_total) * 100
      : 0;

  const getStatusColor = () => {
    if (percentageRemaining > 50) return '#28a745'; // Green
    if (percentageRemaining > 25) return '#ffc107'; // Yellow
    return '#dc3545'; // Red
  };

  const formatPeriod = () => {
    if (entitlement.period_start && entitlement.period_end) {
      return `${format(entitlement.period_start, 'MMM d')} - ${format(entitlement.period_end, 'MMM d, yyyy')}`;
    }
    if (entitlement.expires_at) {
      return `Expires: ${format(entitlement.expires_at, 'MMM d, yyyy')}`;
    }
    return 'No expiration';
  };

  return (
    <div className="entitlement-card">
      <div className="entitlement-card-header">
        <div className="entitlement-icon">{icon}</div>
        <div className="entitlement-title">
          <h3>{label}</h3>
          <div className="entitlement-period">{formatPeriod()}</div>
        </div>
      </div>

      <div className="entitlement-stats">
        <div className="entitlement-remaining">
          <span className="entitlement-number" style={{ color: getStatusColor() }}>
            {entitlement.quantity_remaining}
          </span>
          <span className="entitlement-label">remaining</span>
        </div>

        {entitlement.quantity_total > 0 && (
          <div className="entitlement-total">
            of {entitlement.quantity_total} total
          </div>
        )}
      </div>

      {entitlement.quantity_total > 0 && (
        <div className="entitlement-progress">
          <div
            className="entitlement-progress-bar"
            style={{
              width: `${percentageRemaining}%`,
              backgroundColor: getStatusColor(),
            }}
          />
        </div>
      )}

      {entitlement.type === 'video_course_access' && entitlement.meta.courses && (
        <div className="entitlement-courses">
          <div className="entitlement-courses-label">Enrolled Courses:</div>
          <div className="entitlement-courses-list">
            {entitlement.meta.courses.map((course: string, index: number) => (
              <span key={index} className="entitlement-course-tag">
                {course}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default EntitlementCard;








