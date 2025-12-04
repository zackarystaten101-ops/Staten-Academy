<?php
/**
 * Production Environment Configuration Template
 * 
 * IMPORTANT: 
 * - Copy this file to env.php on your production server
 * - Fill in your actual production values
 * - NEVER commit env.php to version control
 * - Keep a secure backup of this file
 * 
 * This file contains sensitive credentials for production use.
 */

// ===========================================
// Database Configuration
// Get these from Banahosting cPanel → MySQL Databases
// ===========================================
define('DB_HOST', 'localhost'); // Usually 'localhost' for shared hosting
define('DB_USERNAME', 'YOUR_PRODUCTION_DB_USERNAME'); // e.g., yourusername_dbuser
define('DB_PASSWORD', 'YOUR_PRODUCTION_DB_PASSWORD'); // Strong password from cPanel
define('DB_NAME', 'YOUR_PRODUCTION_DB_NAME'); // e.g., yourusername_statenacademy

// ===========================================
// Stripe API Keys (Production)
// Get these from https://dashboard.stripe.com/apikeys
// Make sure you're using LIVE keys (sk_live_... and pk_live_...)
// ===========================================
define('STRIPE_SECRET_KEY', 'sk_live_YOUR_PRODUCTION_SECRET_KEY');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_YOUR_PRODUCTION_PUBLISHABLE_KEY');

// ===========================================
// Google OAuth Configuration (Production)
// Get these from https://console.cloud.google.com/
// IMPORTANT: Update redirect URI in Google Console too!
// ===========================================
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// Replace 'yourdomain.com' with your actual production domain
define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/google-calendar-callback.php');
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/calendar');

// ===========================================
// Application Settings (Production)
// ===========================================
define('APP_ENV', 'production'); // Must be 'production' for live site
define('APP_DEBUG', false); // Must be false in production for security

?>





