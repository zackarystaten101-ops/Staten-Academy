<?php
/**
 * Production Environment Verification Script
 * 
 * This script helps verify your production configuration is correct.
 * 
 * IMPORTANT: Delete this file after verification for security!
 * 
 * Usage: Visit https://yourdomain.com/verify-production.php in your browser
 *        (Make sure to delete it afterwards!)
 */

// Prevent direct access in production (uncomment after first check)
// if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== 'YOUR_IP') {
//     die('Access denied');
// }

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Production Configuration Verification</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .check-item { margin: 10px 0; padding: 10px; background: #f5f5f5; border-left: 4px solid #ddd; }
        .check-item.pass { border-left-color: green; }
        .check-item.fail { border-left-color: red; }
        .check-item.warn { border-left-color: orange; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Production Configuration Verification</h1>
    <p class="warning"><strong>⚠️ SECURITY WARNING:</strong> Delete this file immediately after verification!</p>
    
    <?php
    $checks = [];
    $allPassed = true;
    
    // Check if env.php exists
    if (file_exists(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
        $checks[] = ['name' => 'env.php file exists', 'status' => 'pass'];
    } else {
        $checks[] = ['name' => 'env.php file exists', 'status' => 'fail', 'message' => 'env.php file not found!'];
        $allPassed = false;
    }
    
    // Check APP_ENV
    if (defined('APP_ENV')) {
        if (APP_ENV === 'production') {
            $checks[] = ['name' => 'APP_ENV is set to production', 'status' => 'pass'];
        } else {
            $checks[] = ['name' => 'APP_ENV is set to production', 'status' => 'fail', 'message' => 'APP_ENV is set to: ' . APP_ENV];
            $allPassed = false;
        }
    } else {
        $checks[] = ['name' => 'APP_ENV is defined', 'status' => 'fail', 'message' => 'APP_ENV constant not found'];
        $allPassed = false;
    }
    
    // Check APP_DEBUG
    if (defined('APP_DEBUG')) {
        if (APP_DEBUG === false) {
            $checks[] = ['name' => 'APP_DEBUG is set to false', 'status' => 'pass'];
        } else {
            $checks[] = ['name' => 'APP_DEBUG is set to false', 'status' => 'fail', 'message' => 'APP_DEBUG is set to: ' . (APP_DEBUG ? 'true' : 'false')];
            $allPassed = false;
        }
    } else {
        $checks[] = ['name' => 'APP_DEBUG is defined', 'status' => 'fail', 'message' => 'APP_DEBUG constant not found'];
        $allPassed = false;
    }
    
    // Check Database Configuration
    if (defined('DB_HOST') && defined('DB_USERNAME') && defined('DB_NAME')) {
        $checks[] = ['name' => 'Database constants defined', 'status' => 'pass'];
        
        // Test database connection
        if (defined('DB_PASSWORD')) {
            try {
                $testConn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
                if ($testConn->connect_error) {
                    $checks[] = ['name' => 'Database connection', 'status' => 'fail', 'message' => 'Connection failed: ' . $testConn->connect_error];
                    $allPassed = false;
                } else {
                    $checks[] = ['name' => 'Database connection', 'status' => 'pass'];
                    $testConn->close();
                }
            } catch (Exception $e) {
                $checks[] = ['name' => 'Database connection', 'status' => 'fail', 'message' => 'Error: ' . $e->getMessage()];
                $allPassed = false;
            }
        } else {
            $checks[] = ['name' => 'Database password defined', 'status' => 'warn', 'message' => 'DB_PASSWORD not defined'];
        }
    } else {
        $checks[] = ['name' => 'Database constants defined', 'status' => 'fail', 'message' => 'Missing database constants'];
        $allPassed = false;
    }
    
    // Check Stripe Keys
    if (defined('STRIPE_SECRET_KEY') && defined('STRIPE_PUBLISHABLE_KEY')) {
        $stripeSecret = STRIPE_SECRET_KEY;
        $stripePublic = STRIPE_PUBLISHABLE_KEY;
        
        if (strpos($stripeSecret, 'sk_live_') === 0) {
            $checks[] = ['name' => 'Stripe Secret Key (Production)', 'status' => 'pass'];
        } elseif (strpos($stripeSecret, 'sk_test_') === 0) {
            $checks[] = ['name' => 'Stripe Secret Key (Production)', 'status' => 'warn', 'message' => 'Using test key (sk_test_) instead of live key'];
        } else {
            $checks[] = ['name' => 'Stripe Secret Key (Production)', 'status' => 'fail', 'message' => 'Invalid Stripe secret key format'];
            $allPassed = false;
        }
        
        if (strpos($stripePublic, 'pk_live_') === 0) {
            $checks[] = ['name' => 'Stripe Publishable Key (Production)', 'status' => 'pass'];
        } elseif (strpos($stripePublic, 'pk_test_') === 0) {
            $checks[] = ['name' => 'Stripe Publishable Key (Production)', 'status' => 'warn', 'message' => 'Using test key (pk_test_) instead of live key'];
        } else {
            $checks[] = ['name' => 'Stripe Publishable Key (Production)', 'status' => 'fail', 'message' => 'Invalid Stripe publishable key format'];
            $allPassed = false;
        }
    } else {
        $checks[] = ['name' => 'Stripe keys defined', 'status' => 'fail', 'message' => 'Stripe keys not defined'];
        $allPassed = false;
    }
    
    // Check Google OAuth Configuration
    if (defined('GOOGLE_CLIENT_ID') && defined('GOOGLE_REDIRECT_URI')) {
        $checks[] = ['name' => 'Google OAuth constants defined', 'status' => 'pass'];
        
        $redirectUri = GOOGLE_REDIRECT_URI;
        if (strpos($redirectUri, 'https://') === 0) {
            $checks[] = ['name' => 'Google Redirect URI uses HTTPS', 'status' => 'pass'];
        } elseif (strpos($redirectUri, 'http://localhost') === 0) {
            $checks[] = ['name' => 'Google Redirect URI uses HTTPS', 'status' => 'fail', 'message' => 'Still using localhost redirect URI: ' . $redirectUri];
            $allPassed = false;
        } else {
            $checks[] = ['name' => 'Google Redirect URI uses HTTPS', 'status' => 'warn', 'message' => 'Redirect URI: ' . $redirectUri];
        }
        
        if (defined('GOOGLE_CLIENT_SECRET')) {
            if (GOOGLE_CLIENT_SECRET === 'YOUR_CLIENT_SECRET_HERE' || empty(GOOGLE_CLIENT_SECRET)) {
                $checks[] = ['name' => 'Google Client Secret configured', 'status' => 'warn', 'message' => 'Client secret appears to be placeholder'];
            } else {
                $checks[] = ['name' => 'Google Client Secret configured', 'status' => 'pass'];
            }
        } else {
            $checks[] = ['name' => 'Google Client Secret defined', 'status' => 'fail', 'message' => 'GOOGLE_CLIENT_SECRET not defined'];
            $allPassed = false;
        }
    } else {
        $checks[] = ['name' => 'Google OAuth constants defined', 'status' => 'fail', 'message' => 'Google OAuth constants missing'];
        $allPassed = false;
    }
    
    // Check HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    if ($isHttps) {
        $checks[] = ['name' => 'HTTPS is enabled', 'status' => 'pass'];
    } else {
        $checks[] = ['name' => 'HTTPS is enabled', 'status' => 'warn', 'message' => 'Site is not using HTTPS'];
    }
    
    // Check .htaccess
    if (file_exists(__DIR__ . '/.htaccess')) {
        $checks[] = ['name' => '.htaccess file exists', 'status' => 'pass'];
    } else {
        $checks[] = ['name' => '.htaccess file exists', 'status' => 'warn', 'message' => '.htaccess file not found'];
    }
    
    // Display results
    echo '<h2>Verification Results</h2>';
    foreach ($checks as $check) {
        $statusClass = $check['status'];
        $icon = $check['status'] === 'pass' ? '✅' : ($check['status'] === 'fail' ? '❌' : '⚠️');
        echo '<div class="check-item ' . $statusClass . '">';
        echo '<strong>' . $icon . ' ' . $check['name'] . '</strong>';
        if (isset($check['message'])) {
            echo '<br><span class="' . $statusClass . '">' . htmlspecialchars($check['message']) . '</span>';
        }
        echo '</div>';
    }
    
    echo '<h2>Summary</h2>';
    if ($allPassed) {
        echo '<div class="check-item pass"><strong>✅ All critical checks passed!</strong></div>';
    } else {
        echo '<div class="check-item fail"><strong>❌ Some checks failed. Please review and fix the issues above.</strong></div>';
    }
    
    echo '<h2>Next Steps</h2>';
    echo '<ol>';
    echo '<li>Review all check results above</li>';
    echo '<li>Fix any failed checks</li>';
    echo '<li><strong>DELETE THIS FILE</strong> for security: <code>verify-production.php</code></li>';
    echo '<li>Test your website functionality</li>';
    echo '</ol>';
    ?>
    
    <hr>
    <p class="info"><small>Generated: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





