<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "Privacy Policy - Staten Academy";
$page_description = "Privacy Policy for Staten Academy";

$user = null;
if (isset($_SESSION['user_id'])) {
    $user = getUserById($conn, $_SESSION['user_id']);
}

if (!function_exists('getAssetPath')) {
    function getAssetPath($asset) {
        $asset = ltrim($asset, '/');
        if (strpos($asset, 'assets/') === 0) {
            $assetPath = $asset;
        } else {
            $assetPath = 'assets/' . $asset;
        }
        return '/' . $assetPath;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/app/Views/components/header.php'; ?>
    
    <main style="max-width: 900px; margin: 40px auto; padding: 20px; line-height: 1.8;">
        <h1>Privacy Policy</h1>
        <p><em>Last updated: <?php echo date('F j, Y'); ?></em></p>
        
        <section style="margin: 30px 0;">
            <h2>1. Information We Collect</h2>
            <p>We collect information that you provide directly to us, including:</p>
            <ul>
                <li>Name, email address, and contact information</li>
                <li>Account credentials</li>
                <li>Payment information (processed securely through Stripe)</li>
                <li>Learning preferences and goals</li>
                <li>Lesson history and interactions</li>
            </ul>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>2. How We Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Provide, maintain, and improve our services</li>
                <li>Process payments and manage subscriptions</li>
                <li>Communicate with you about your account and lessons</li>
                <li>Personalize your learning experience</li>
                <li>Send you updates and promotional materials (with your consent)</li>
            </ul>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>3. Information Sharing</h2>
            <p>We do not sell your personal information. We may share your information only:</p>
            <ul>
                <li>With teachers to facilitate lessons</li>
                <li>With service providers (like Stripe for payments) who assist in operating our platform</li>
                <li>When required by law or to protect our rights</li>
            </ul>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>4. Data Security</h2>
            <p>We implement appropriate security measures to protect your personal information. However, no method of transmission over the internet is 100% secure.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>5. Your Rights</h2>
            <p>You have the right to:</p>
            <ul>
                <li>Access your personal information</li>
                <li>Correct inaccurate information</li>
                <li>Request deletion of your information</li>
                <li>Opt out of promotional communications</li>
            </ul>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>6. Cookies</h2>
            <p>We use cookies to enhance your experience, analyze usage, and assist in our marketing efforts. You can control cookies through your browser settings.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>7. Contact Us</h2>
            <p>If you have questions about this Privacy Policy, please contact us through your dashboard or email.</p>
        </section>
    </main>
    
    <?php include __DIR__ . '/app/Views/components/footer.php'; ?>
</body>
</html>

