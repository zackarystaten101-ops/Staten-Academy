<?php
/**
 * Environment Configuration Example
 * 
 * Copy this file to env.php and fill in your actual values.
 * NEVER commit env.php to version control!
 */

// ===========================================
// Database Configuration
// ===========================================
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'staten_academy');

// ===========================================
// Stripe API Keys
// Get these from https://dashboard.stripe.com/apikeys
// Use test keys for development (sk_test_..., pk_test_...)
// Use live keys for production (sk_live_..., pk_live_...)
// ===========================================
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_TEST_SECRET_KEY_HERE');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_TEST_PUBLISHABLE_KEY_HERE');

// Stripe Webhook Secret
// Get this from https://dashboard.stripe.com/webhooks
// Create a webhook endpoint pointing to: https://yourdomain.com/stripe-webhook.php
// Copy the "Signing secret" (starts with whsec_...)
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET_HERE');

// Stripe Product IDs for Categories
// Create products in Stripe Dashboard and get their Price IDs
// ===========================================
define('STRIPE_PRODUCT_TRIAL', 'price_YOUR_TRIAL_PRICE_ID_HERE'); // $25 trial lesson
define('STRIPE_PRODUCT_KIDS', 'price_YOUR_KIDS_PLAN_PRICE_ID_HERE'); // Young Learners category
define('STRIPE_PRODUCT_ADULTS', 'price_YOUR_ADULTS_PLAN_PRICE_ID_HERE'); // Adults category
define('STRIPE_PRODUCT_CODING', 'price_YOUR_CODING_PLAN_PRICE_ID_HERE'); // English for Coding category

// Wallet API URL (TypeScript backend)
// ===========================================
define('WALLET_API_URL', 'http://localhost:3001/api'); // Default: localhost:3001

// ===========================================
// Google OAuth Configuration
// Get these from https://console.cloud.google.com/
// 1. Create a project
// 2. Enable Google Calendar API
// 3. Create OAuth 2.0 credentials (Web Application)
// 4. Add authorized redirect URIs (see instructions below)
// ===========================================
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID_HERE.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');

// Google OAuth Redirect URI Configuration
// IMPORTANT: You must add the redirect URI to Google Console for both environments
//
// OPTION 1: Manual Configuration (Recommended for production)
// Set the exact URL that matches your server setup:
// - For localhost: 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php'
// - For production: 'https://yourdomain.com/google-calendar-callback.php'
// Make sure to URL-encode spaces as %20
define('GOOGLE_REDIRECT_URI', 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php');

// OPTION 2: Dynamic Configuration (Auto-detection)
// Uncomment the block below to automatically generate redirect URI based on current server
// Note: You still need to add BOTH localhost AND production URLs to Google Console
// if (!defined('GOOGLE_REDIRECT_URI')) {
//     $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
//     $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
//     $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
//     $scriptPath = str_replace('\\', '/', $scriptPath);
//     if ($scriptPath === '.' || $scriptPath === '/') $scriptPath = '';
//     $scriptPath = rtrim($scriptPath, '/');
//     define('GOOGLE_REDIRECT_URI', $protocol . '://' . $host . $scriptPath . '/google-calendar-callback.php');
// }

define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/calendar');

// ===========================================
// Application Settings
// ===========================================
define('APP_ENV', 'development'); // 'development' or 'production'
define('APP_DEBUG', true); // Set to false in production

// IMPORTANT: Do NOT add any whitespace, newlines, or characters after this closing tag
// Best practice: Remove the closing ?> tag entirely (PHP doesn't require it)
