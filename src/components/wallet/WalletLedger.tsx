/**
 * WalletLedger Component
 * Shows transaction history
 */

import React, { useState, useEffect } from 'react';
import { format } from 'date-fns';
import './WalletLedger.css';

export interface WalletItem {
  id: string;
  type: 'entitlement_purchase' | 'entitlement_used' | 'entitlement_refund' | 'plan_subscription' | 'adjustment';
  reference_id: string | null;
  amount: number;
  status: 'pending' | 'confirmed' | 'failed';
  meta: Record<string, any>;
  created_at: Date;
}

interface WalletLedgerProps {
  userId: number;
  apiBaseUrl?: string;
}

const WalletLedger: React.FC<WalletLedgerProps> = ({
  userId,
  apiBaseUrl = 'http://localhost:3001',
}) => {
  const [ledger, setLedger] = useState<WalletItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchLedger();
  }, [userId]);

  const fetchLedger = async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `${apiBaseUrl}/api/wallets/${userId}/ledger`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json',
          },
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch ledger');
      }

      const data = await response.json();
      setLedger(
        data.ledger.map((item: any) => ({
          ...item,
          created_at: new Date(item.created_at),
        }))
      );
    } catch (error) {
      console.error('Error fetching ledger:', error);
    } finally {
      setLoading(false);
    }
  };

  const getTypeLabel = (type: WalletItem['type']): string => {
    switch (type) {
      case 'entitlement_purchase':
        return 'Entitlement Purchased';
      case 'entitlement_used':
        return 'Class Booked';
      case 'entitlement_refund':
        return 'Refund';
      case 'plan_subscription':
        return 'Plan Subscription';
      case 'adjustment':
        return 'Adjustment';
      default:
        return type;
    }
  };

  const getStatusClass = (status: WalletItem['status']): string => {
    switch (status) {
      case 'confirmed':
        return 'status-confirmed';
      case 'pending':
        return 'status-pending';
      case 'failed':
        return 'status-failed';
      default:
        return '';
    }
  };

  if (loading) {
    return <div className="wallet-ledger-loading">Loading transaction history...</div>;
  }

  return (
    <div className="wallet-ledger">
      <h2>Transaction History</h2>
      {ledger.length === 0 ? (
        <div className="wallet-ledger-empty">No transactions yet</div>
      ) : (
        <table className="wallet-ledger-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {ledger.map((item) => (
              <tr key={item.id}>
                <td>{format(item.created_at, 'MMM d, yyyy h:mm a')}</td>
                <td>{getTypeLabel(item.type)}</td>
                <td>${item.amount.toFixed(2)}</td>
                <td>
                  <span className={`wallet-ledger-status ${getStatusClass(item.status)}`}>
                    {item.status}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default WalletLedger;



