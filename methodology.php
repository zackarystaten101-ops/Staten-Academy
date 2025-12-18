<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "Teaching Methodology - Staten Academy";
$page_description = "Learn about Staten Academy's teaching methodology";

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
        <h1>Our Teaching Methodology</h1>
        
        <section style="margin: 40px 0;">
            <h2>Student-Centered Learning</h2>
            <p>At Staten Academy, we believe in personalized learning. Our teachers adapt their approach to each student's learning style, goals, and pace. Students choose their teachers and schedule lessons that fit their lives.</p>
        </section>
        
        <section style="margin: 40px 0;">
            <h2>Interactive Classes</h2>
            <p>All lessons are 50 minutes of focused, interactive instruction via Google Meet. Our teachers use engaging materials, real-world scenarios, and conversation practice to make learning effective and enjoyable.</p>
        </section>
        
        <section style="margin: 40px 0;">
            <h2>Flexible Structure</h2>
            <p>Students can book 1-on-1 classes or join group classes based on their subscription plan. This flexibility allows learners to balance individual attention with peer interaction.</p>
        </section>
        
        <section style="margin: 40px 0;">
            <h2>Expert Teachers</h2>
            <p>All our teachers are carefully selected and continuously trained. They specialize in different categories (Kids, Adults, Coding) and bring expertise to every lesson.</p>
        </section>
        
        <section style="margin: 40px 0;">
            <h2>Progress Tracking</h2>
            <p>Students can set learning goals, track their progress, and receive feedback from teachers. This helps maintain motivation and ensures continuous improvement.</p>
        </section>
    </main>
    
    <?php include __DIR__ . '/app/Views/components/footer.php'; ?>
</body>
</html>

