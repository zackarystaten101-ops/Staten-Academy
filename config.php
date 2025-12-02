<?php
/**
 * Application Configuration
 * 
 * This file loads environment-specific configuration.
 * All sensitive credentials are stored in env.php
 */

// Load environment configuration if not already loaded
if (!defined('STRIPE_SECRET_KEY')) {
    require_once __DIR__ . '/env.php';
}

// Stripe keys are now defined in env.php:
// - STRIPE_SECRET_KEY
// - STRIPE_PUBLISHABLE_KEY
?>
