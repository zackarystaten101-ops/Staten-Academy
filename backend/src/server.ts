/**
 * Staten Academy Backend Server
 * Express API server for Wallet, Calendar, and Earnings system
 */

import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import { config } from './config/env.js';
import { pool } from './config/database.js';

// Import routes
import walletRoutes from './routes/wallet.js';
import bookingRoutes from './routes/booking.js';
import calendarRoutes from './routes/calendar.js';
import earningsRoutes from './routes/earnings.js';
import adminRoutes from './routes/admin.js';
import recurringRoutes from './routes/recurring.js';
import { requireFeature } from './config/feature-flags.js';

dotenv.config();

const app = express();
const PORT = config.port;

// Middleware
app.use(cors({
  origin: process.env.FRONTEND_URL || 'http://localhost:5173',
  credentials: true,
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Health check (always available, no feature flag)
app.get('/health', async (req, res) => {
  try {
    await pool.query('SELECT NOW()');
    res.json({
      status: 'ok',
      timestamp: new Date().toISOString(),
      database: 'connected',
      features: {
        walletV2: config.features.walletV2Enabled,
        calendarV2: config.features.calendarV2Enabled,
      },
    });
  } catch (error) {
    res.status(500).json({
      status: 'error',
      database: 'disconnected',
      error: error instanceof Error ? error.message : 'Unknown error',
    });
  }
});

// API Routes with feature flags
app.use('/api/wallets', requireFeature('walletV2'), walletRoutes);
app.use('/api/bookings', requireFeature('walletV2'), bookingRoutes);
app.use('/api/calendar', requireFeature('calendarV2'), calendarRoutes);
app.use('/api/earnings', requireFeature('walletV2'), earningsRoutes);
app.use('/api/admin', adminRoutes); // Admin always enabled
app.use('/api/recurring', requireFeature('walletV2'), recurringRoutes);

// 404 handler
app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// Error handler
app.use((err: any, req: express.Request, res: express.Response, next: express.NextFunction) => {
  console.error('Error:', err);
  res.status(err.status || 500).json({
    error: err.message || 'Internal server error',
    ...(config.nodeEnv === 'development' && { stack: err.stack }),
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`🚀 Staten Academy Backend API running on port ${PORT}`);
  console.log(`📊 Environment: ${config.nodeEnv}`);
  console.log(`🔧 Features: Wallet V2: ${config.features.walletV2Enabled}, Calendar V2: ${config.features.calendarV2Enabled}`);
});

export default app;

