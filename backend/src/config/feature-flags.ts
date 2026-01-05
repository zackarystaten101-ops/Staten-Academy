/**
 * Feature Flags
 * Control which features are enabled
 * Set via environment variables for easy toggling
 */

import { config } from './env.js';

export const featureFlags = {
  walletV2Enabled: config.features.walletV2Enabled,
  calendarV2Enabled: config.features.calendarV2Enabled,
};

/**
 * Middleware to check feature flags
 * Returns 503 if feature is disabled
 */
export function requireFeature(feature: 'walletV2' | 'calendarV2') {
  return (req: any, res: any, next: any) => {
    const isEnabled = feature === 'walletV2' 
      ? featureFlags.walletV2Enabled
      : featureFlags.calendarV2Enabled;
    
    if (!isEnabled) {
      return res.status(503).json({
        error: `Feature ${feature} is currently disabled`,
        code: 'FEATURE_DISABLED',
      });
    }
    
    next();
  };
}







