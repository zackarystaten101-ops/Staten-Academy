/**
 * WalletView Component
 * Shows student entitlements (NOT credits-based)
 * Displays: One-on-One classes remaining, Group classes, Video course access
 */

import React, { useState, useEffect } from 'react';
import EntitlementCard from './EntitlementCard';
import WalletLedger from './WalletLedger';
import './WalletView.css';

export interface Entitlement {
  id: string;
  student_id: number;
  type: 'one_on_one_class' | 'group_class' | 'video_course_access' | 'practice_session';
  quantity_total: number;
  quantity_remaining: number;
  period_start: Date | null;
  period_end: Date | null;
  expires_at: Date | null;
  meta: Record<string, any>;
}

interface WalletViewProps {
  userId: number;
  apiBaseUrl?: string;
}

const WalletView: React.FC<WalletViewProps> = ({
  userId,
  apiBaseUrl = 'http://localhost:3001',
}) => {
  const [entitlements, setEntitlements] = useState<Entitlement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showLedger, setShowLedger] = useState(false);

  useEffect(() => {
    fetchEntitlements();
  }, [userId]);

  const fetchEntitlements = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(
        `${apiBaseUrl}/api/wallets/${userId}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch entitlements');
      }

      const data = await response.json();
      setEntitlements(
        data.entitlements.map((e: any) => ({
          ...e,
          period_start: e.period_start ? new Date(e.period_start) : null,
          period_end: e.period_end ? new Date(e.period_end) : null,
          expires_at: e.expires_at ? new Date(e.expires_at) : null,
        }))
      );
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const getEntitlementTypeLabel = (type: Entitlement['type']): string => {
    switch (type) {
      case 'one_on_one_class':
        return 'One-on-One Classes';
      case 'group_class':
        return 'Group Classes';
      case 'video_course_access':
        return 'Video Course Access';
      case 'practice_session':
        return 'Practice Sessions';
      default:
        return type;
    }
  };

  const getEntitlementIcon = (type: Entitlement['type']): string => {
    switch (type) {
      case 'one_on_one_class':
        return '👤';
      case 'group_class':
        return '👥';
      case 'video_course_access':
        return '🎥';
      case 'practice_session':
        return '💪';
      default:
        return '📚';
    }
  };

  if (loading) {
    return (
      <div className="wallet-view">
        <div className="wallet-loading">Loading your wallet...</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="wallet-view">
        <div className="wallet-error">Error: {error}</div>
      </div>
    );
  }

  return (
    <div className="wallet-view">
      <div className="wallet-header">
        <h1>My Wallet</h1>
        <button
          className="wallet-ledger-toggle"
          onClick={() => setShowLedger(!showLedger)}
        >
          {showLedger ? 'Hide' : 'Show'} Transaction History
        </button>
      </div>

      <div className="wallet-content">
        {showLedger ? (
          <WalletLedger userId={userId} apiBaseUrl={apiBaseUrl} />
        ) : (
          <div className="wallet-entitlements">
            {entitlements.length === 0 ? (
              <div className="wallet-empty">
                <p>No entitlements found.</p>
                <p>Purchase a plan to get started!</p>
              </div>
            ) : (
              entitlements.map((entitlement) => (
                <EntitlementCard
                  key={entitlement.id}
                  entitlement={entitlement}
                  label={getEntitlementTypeLabel(entitlement.type)}
                  icon={getEntitlementIcon(entitlement.type)}
                />
              ))
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default WalletView;







