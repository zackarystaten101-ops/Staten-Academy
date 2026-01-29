<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "FAQ - Staten Academy";
$page_description = "Frequently asked questions about Staten Academy";

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
    
    <main style="max-width: 1200px; margin: 40px auto; padding: 20px;">
        <h1>Frequently Asked Questions</h1>
        
        <div style="margin-top: 30px;">
            <h2>General Questions</h2>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>What is Staten Academy?</h3>
                <p>Staten Academy is an online English learning platform offering Group Classes for kids ages 3-11. Our fun, interactive classes help children learn English with peers in a social environment. For just $129.99/month, students get 3 classes per week (12 per month).</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>How does the platform work?</h3>
                <p>Students browse approved teachers in their chosen category, select a teacher, and book lessons using credits. All classes are conducted via Google Meet. Teachers are carefully selected and managed by Staten Academy.</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>What are credits?</h3>
                <p>Credits are used to book lessons. Each lesson requires 1 credit. You can purchase credits through subscription plans or class packages. Credits are added to your account when you subscribe or purchase a package.</p>
            </div>
            
            <h2>Subscription & Pricing</h2>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>How do subscriptions work?</h3>
                <p>Subscriptions are monthly recurring plans that automatically renew. Credits are added to your account each month on your billing cycle date. You can manage your subscription, update payment methods, and cancel through the Stripe Customer Portal.</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Can I cancel my subscription?</h3>
                <p>Yes, you can cancel your subscription at any time through the Stripe Customer Portal. Your existing credits will remain available, but new credits will stop being added after cancellation.</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>What happens if my payment fails?</h3>
                <p>If a subscription payment fails, you'll be notified via email. Your booking access may be paused until the payment issue is resolved. Please update your payment method through the Stripe Customer Portal.</p>
            </div>
            
            <h2>Booking & Classes</h2>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>How do I book a lesson?</h3>
                <p>Browse teachers in your category, view their availability, and select a time slot. Each lesson costs 1 credit. Ensure you have sufficient credits before booking.</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Can I cancel or reschedule a lesson?</h3>
                <p>Yes, you can cancel or reschedule lessons through your dashboard. Please check our cancellation policy for details on timing and credit refunds.</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>How long are the lessons?</h3>
                <p>All lessons are 50 minutes long.</p>
            </div>
            
            <h2>Teachers</h2>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>How are teachers selected?</h3>
                <p>All teachers are carefully vetted and approved by Staten Academy. We ensure they meet our quality standards before they can teach on the platform.</p>
            </div>
            
            <div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Can I choose my teacher?</h3>
                <p>Yes! Students browse and select their preferred teachers from the approved list in their category.</p>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/app/Views/components/footer.php'; ?>
</body>
</html>

