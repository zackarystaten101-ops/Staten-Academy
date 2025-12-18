<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "Terms of Service - Staten Academy";
$page_description = "Terms of Service for Staten Academy";

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
        <h1>Terms of Service</h1>
        <p><em>Last updated: <?php echo date('F j, Y'); ?></em></p>
        
        <section style="margin: 30px 0;">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using Staten Academy, you accept and agree to be bound by the terms and provision of this agreement.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>2. Use License</h2>
            <p>Permission is granted to temporarily use Staten Academy for personal, non-commercial use. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
            <ul>
                <li>Modify or copy the materials</li>
                <li>Use the materials for any commercial purpose or for any public display</li>
                <li>Attempt to decompile or reverse engineer any software contained on Staten Academy</li>
                <li>Remove any copyright or other proprietary notations from the materials</li>
            </ul>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>3. User Accounts</h2>
            <p>You are responsible for maintaining the confidentiality of your account and password. You agree to accept responsibility for all activities that occur under your account.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>4. Payment Terms</h2>
            <p>All payments are processed through Stripe. Subscriptions automatically renew monthly unless cancelled. Refunds are handled according to our refund policy.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>5. Cancellation & Refund Policy</h2>
            <p>You may cancel your subscription at any time through the Stripe Customer Portal. Existing credits remain valid after cancellation. Refund requests are handled on a case-by-case basis.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>6. Code of Conduct</h2>
            <p>Users must conduct themselves respectfully during lessons and interactions. Inappropriate behavior may result in account suspension or termination.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>7. Limitation of Liability</h2>
            <p>Staten Academy shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of the platform.</p>
        </section>
        
        <section style="margin: 30px 0;">
            <h2>8. Changes to Terms</h2>
            <p>Staten Academy reserves the right to revise these terms at any time. By using this platform, you are agreeing to be bound by the then current version of these Terms of Service.</p>
        </section>
    </main>
    
    <?php include __DIR__ . '/app/Views/components/footer.php'; ?>
</body>
</html>

