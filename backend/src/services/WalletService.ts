/**
 * Wallet Service
 * Handles entitlements tracking (NOT credits-based)
 * Entitlements represent plan benefits: one-on-one classes, group classes, video access, etc.
 */

import { query, transaction } from '../config/database.js';
import { v4 as uuidv4 } from 'uuid';

export type EntitlementType = 'one_on_one_class' | 'group_class' | 'video_course_access' | 'practice_session';

export interface Entitlement {
  id: string;
  student_id: number;
  type: EntitlementType;
  quantity_total: number;
  quantity_remaining: number;
  period_start: Date | null;
  period_end: Date | null;
  expires_at: Date | null;
  meta: Record<string, any>;
  created_at: Date;
  updated_at: Date;
}

export interface WalletItem {
  id: string;
  wallet_id: string;
  type: 'entitlement_purchase' | 'entitlement_used' | 'entitlement_refund' | 'plan_subscription' | 'adjustment';
  reference_id: string | null;
  credits_delta: number;
  amount: number;
  status: 'pending' | 'confirmed' | 'failed';
  meta: Record<string, any>;
  created_at: Date;
}

/**
 * Get all entitlements for a student
 */
export async function getEntitlements(studentId: number): Promise<Entitlement[]> {
  const result = await query(
    `SELECT * FROM entitlements 
     WHERE student_id = $1 
     AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY type, created_at DESC`,
    [studentId]
  );
  
  return result.rows.map(row => ({
    ...row,
    period_start: row.period_start ? new Date(row.period_start) : null,
    period_end: row.period_end ? new Date(row.period_end) : null,
    expires_at: row.expires_at ? new Date(row.expires_at) : null,
    created_at: new Date(row.created_at),
    updated_at: new Date(row.updated_at),
    meta: row.meta || {},
  }));
}

/**
 * Get a specific entitlement by type
 */
export async function getEntitlementByType(
  studentId: number,
  type: EntitlementType
): Promise<Entitlement | null> {
  const result = await query(
    `SELECT * FROM entitlements 
     WHERE student_id = $1 AND type = $2
     AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY created_at DESC
     LIMIT 1`,
    [studentId, type]
  );
  
  if (result.rows.length === 0) {
    return null;
  }
  
  const row = result.rows[0];
  return {
    ...row,
    period_start: row.period_start ? new Date(row.period_start) : null,
    period_end: row.period_end ? new Date(row.period_end) : null,
    expires_at: row.expires_at ? new Date(row.expires_at) : null,
    created_at: new Date(row.created_at),
    updated_at: new Date(row.updated_at),
    meta: row.meta || {},
  };
}

/**
 * Hold an entitlement for a booking (temporary reservation)
 * Returns the entitlement ID if successful
 */
export async function holdEntitlement(
  studentId: number,
  type: EntitlementType,
  classId: string
): Promise<string> {
  return transaction(async (client) => {
    // Find an available entitlement
    const entitlementResult = await client.query(
      `SELECT id, quantity_remaining FROM entitlements
       WHERE student_id = $1 AND type = $2
       AND quantity_remaining > 0
       AND (expires_at IS NULL OR expires_at > NOW())
       ORDER BY created_at ASC
       FOR UPDATE SKIP LOCKED
       LIMIT 1`,
      [studentId, type]
    );
    
    if (entitlementResult.rows.length === 0) {
      throw new Error(`No available ${type} entitlements`);
    }
    
    const entitlement = entitlementResult.rows[0];
    
    // Decrement quantity_remaining (this is the hold)
    await client.query(
      `UPDATE entitlements 
       SET quantity_remaining = quantity_remaining - 1,
           updated_at = NOW()
       WHERE id = $1`,
      [entitlement.id]
    );
    
    // Create wallet item for audit
    const walletResult = await client.query(
      `SELECT id FROM wallets WHERE user_id = $1`,
      [studentId]
    );
    
    let walletId: string;
    if (walletResult.rows.length === 0) {
      // Create wallet if it doesn't exist
      const newWallet = await client.query(
        `INSERT INTO wallets (user_id) VALUES ($1) RETURNING id`,
        [studentId]
      );
      walletId = newWallet.rows[0].id;
    } else {
      walletId = walletResult.rows[0].id;
    }
    
    await client.query(
      `INSERT INTO wallet_items (wallet_id, type, reference_id, status, meta)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        walletId,
        'entitlement_used',
        classId,
        'pending', // Will be confirmed when booking is accepted
        JSON.stringify({ entitlement_id: entitlement.id, hold: true }),
      ]
    );
    
    return entitlement.id;
  });
}

/**
 * Confirm entitlement use (when booking is accepted)
 */
export async function confirmEntitlementUse(
  entitlementId: string,
  classId: string,
  studentId: number
): Promise<void> {
  await transaction(async (client) => {
    // Update wallet item status to confirmed
    await client.query(
      `UPDATE wallet_items 
       SET status = 'confirmed'
       WHERE reference_id = $1 AND type = 'entitlement_used'`,
      [classId]
    );
    
    // Log the confirmation
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        studentId,
        'entitlement_confirmed',
        'entitlement',
        entitlementId,
        JSON.stringify({ class_id: classId }),
      ]
    );
  });
}

/**
 * Refund an entitlement (cancellation, teacher cancels, etc.)
 */
export async function refundEntitlement(
  entitlementId: string,
  classId: string,
  reason: string,
  actorId: number
): Promise<void> {
  await transaction(async (client) => {
    // Increment quantity_remaining back
    await client.query(
      `UPDATE entitlements 
       SET quantity_remaining = quantity_remaining + 1,
           updated_at = NOW()
       WHERE id = $1`,
      [entitlementId]
    );
    
    // Create refund wallet item
    const walletResult = await client.query(
      `SELECT w.id FROM wallets w
       JOIN entitlements e ON w.user_id = (SELECT student_id FROM entitlements WHERE id = $1)
       WHERE e.id = $1`,
      [entitlementId]
    );
    
    if (walletResult.rows.length > 0) {
      const walletId = walletResult.rows[0].id;
      
      await client.query(
        `INSERT INTO wallet_items (wallet_id, type, reference_id, status, meta)
         VALUES ($1, $2, $3, $4, $5)`,
        [
          walletId,
          'entitlement_refund',
          classId,
          'confirmed',
          JSON.stringify({ entitlement_id: entitlementId, reason }),
        ]
      );
    }
    
    // Cancel the pending wallet item if exists
    await client.query(
      `UPDATE wallet_items 
       SET status = 'failed'
       WHERE reference_id = $1 AND type = 'entitlement_used' AND status = 'pending'`,
      [classId]
    );
    
    // Audit log
    await client.query(
      `INSERT INTO audit_logs (actor_id, action, target_type, target_id, changes)
       VALUES ($1, $2, $3, $4, $5)`,
      [
        actorId,
        'entitlement_refunded',
        'entitlement',
        entitlementId,
        JSON.stringify({ class_id: classId, reason }),
      ]
    );
  });
}

/**
 * Create entitlements for a student based on their plan
 */
export async function createEntitlementsFromPlan(
  studentId: number,
  planId: number,
  planDetails: {
    oneOnOneClassesPerWeek?: number;
    groupClassesPerMonth?: number;
    videoCourseAccess?: boolean;
  },
  periodStart: Date,
  periodEnd: Date
): Promise<string[]> {
  const entitlementIds: string[] = [];
  
  // Create one-on-one class entitlements
  if (planDetails.oneOnOneClassesPerWeek && planDetails.oneOnOneClassesPerWeek > 0) {
    const monthlyTotal = planDetails.oneOnOneClassesPerWeek * 4; // Approximate monthly
    
    const result = await query(
      `INSERT INTO entitlements 
       (student_id, type, quantity_total, quantity_remaining, period_start, period_end, meta)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id`,
      [
        studentId,
        'one_on_one_class',
        monthlyTotal,
        monthlyTotal,
        periodStart,
        periodEnd,
        JSON.stringify({ plan_id: planId }),
      ]
    );
    
    entitlementIds.push(result.rows[0].id);
  }
  
  // Create group class entitlements
  if (planDetails.groupClassesPerMonth && planDetails.groupClassesPerMonth > 0) {
    const result = await query(
      `INSERT INTO entitlements 
       (student_id, type, quantity_total, quantity_remaining, period_start, period_end, meta)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id`,
      [
        studentId,
        'group_class',
        planDetails.groupClassesPerMonth,
        planDetails.groupClassesPerMonth,
        periodStart,
        periodEnd,
        JSON.stringify({ plan_id: planId }),
      ]
    );
    
    entitlementIds.push(result.rows[0].id);
  }
  
  // Create video course access entitlement (doesn't expire)
  if (planDetails.videoCourseAccess) {
    const result = await query(
      `INSERT INTO entitlements 
       (student_id, type, quantity_total, quantity_remaining, meta)
       VALUES ($1, $2, $3, $4, $5)
       RETURNING id`,
      [
        studentId,
        'video_course_access',
        999999, // Unlimited
        999999,
        JSON.stringify({ plan_id: planId, unlimited: true }),
      ]
    );
    
    entitlementIds.push(result.rows[0].id);
  }
  
  return entitlementIds;
}

/**
 * Get wallet transaction history (ledger)
 */
export async function getWalletLedger(studentId: number, limit: number = 50): Promise<WalletItem[]> {
  const result = await query(
    `SELECT wi.* FROM wallet_items wi
     JOIN wallets w ON wi.wallet_id = w.id
     WHERE w.user_id = $1
     ORDER BY wi.created_at DESC
     LIMIT $2`,
    [studentId, limit]
  );
  
  return result.rows.map(row => ({
    ...row,
    created_at: new Date(row.created_at),
    meta: row.meta || {},
  }));
}

