/**
 * Wallet Routes
 * Handles entitlements display and wallet operations
 */

import express from 'express';
import { requireAuth, requireRole, AuthRequest } from '../middleware/auth.js';
import { sanitizeResponse } from '../middleware/sanitize.js';
import {
  getEntitlements,
  getWalletLedger,
  createEntitlementsFromPlan,
} from '../services/WalletService.js';

const router = express.Router();

// All routes require authentication
router.use(requireAuth);
router.use(sanitizeResponse);

/**
 * GET /api/wallets/:userId
 * Get student entitlements (what they can access)
 */
router.get('/:userId', requireRole(['student', 'admin']), async (req: AuthRequest, res) => {
  try {
    const userId = parseInt(req.params.userId);
    
    // Students can only see their own wallet
    if (req.userRole === 'student' && req.userId !== userId) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    const entitlements = await getEntitlements(userId);
    
    res.json({
      success: true,
      entitlements,
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * GET /api/wallets/:userId/ledger
 * Get wallet transaction history
 */
router.get('/:userId/ledger', requireRole(['student', 'admin']), async (req: AuthRequest, res) => {
  try {
    const userId = parseInt(req.params.userId);
    const limit = parseInt(req.query.limit as string) || 50;
    
    // Students can only see their own ledger
    if (req.userRole === 'student' && req.userId !== userId) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    const ledger = await getWalletLedger(userId, limit);
    
    res.json({
      success: true,
      ledger,
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

/**
 * POST /api/wallets/purchase
 * Purchase additional entitlements (if allowed by plan)
 * TODO: Implement if needed
 */
router.post('/purchase', requireRole(['student', 'admin']), async (req: AuthRequest, res) => {
  try {
    // Placeholder - implement purchase logic if needed
    res.status(501).json({ error: 'Not implemented yet' });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

export default router;



