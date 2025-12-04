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

// ===========================================
// Google OAuth Configuration
// Get these from https://console.cloud.google.com/
// 1. Create a project
// 2. Enable Google Calendar API
// 3. Create OAuth 2.0 credentials (Web Application)
// 4. Add authorized redirect URIs
// ===========================================
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID_HERE.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', 'http://localhost/Web%20page/Staten-Academy/google-calendar-callback.php');
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/calendar');

// ===========================================
// Application Settings
// ===========================================
define('APP_ENV', 'development'); // 'development' or 'production'
define('APP_DEBUG', true); // Set to false in production
?>

