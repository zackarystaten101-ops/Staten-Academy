<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

// Ensure getAssetPath function is available
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

if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); // Clear output buffer before redirect
    header("Location: login.php");
    exit();
}

$materials = $conn->query("SELECT * FROM classroom_materials ORDER BY created_at DESC");

// Fetch user data for header
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Set page title for header
$page_title = 'My Classroom';
$_SESSION['profile_pic'] = $user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#004080">
    <meta name="mobile-web-app-capable" content="yes">
    <title>My Classroom - Staten Academy</title>
    <link rel="stylesheet" href="<?php echo getAssetPath('styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo getAssetPath('css/mobile.css'); ?>">
    <!-- MODERN SHADOWS - To disable, comment out the line below -->
    <link rel="stylesheet" href="<?php echo getAssetPath('css/modern-shadows.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Classroom page specific styles */
        .container { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .material-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; transition: transform 0.2s; }
        .material-card:hover { transform: translateY(-3px); }
        .tag { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; margin-bottom: 10px; }
        .tag.video { background: #e1f0ff; color: #0b6cf5; }
        .tag.link { background: #fff3cd; color: #856404; }
        .tag.file { background: #d4edda; color: #155724; }
        
        .btn-open { 
            float: right; 
            background: #0b6cf5; 
            color: white; 
            text-decoration: none; 
            padding: 8px 15px; 
            border-radius: 5px; 
            font-size: 0.9rem; 
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-open:hover { 
            background: #0056b3; 
            color: white;
        }
        
        /* Fix white buttons in mobile menu */
        .menu-toggle, .close-btn {
            background: transparent;
            border: none;
            color: #004080;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px 10px;
        }
        .menu-toggle:hover, .close-btn:hover {
            background: rgba(0, 64, 128, 0.1);
        }
        
        /* Ensure nav buttons have proper styling */
        .nav-btn {
            background: white;
            color: #004080;
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: block;
            margin: 5px 0;
            transition: all 0.2s;
        }
        .nav-btn:hover {
            background: #f0f7ff;
            border-color: #0b6cf5;
            color: #0b6cf5;
        }
        
        /* Hide hamburger menu and mobile menu on desktop for pages with sidebar */
        @media (min-width: 769px) {
            .menu-toggle {
                display: none !important;
            }
            #mobile-menu {
                display: none !important;
                visibility: hidden !important;
            }
            .mobile-backdrop {
                display: none !important;
            }
        }
        
        /* Show hamburger menu on mobile */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block !important;
            }
        }
    </style>
</head>
<body class="dashboard-layout">
<?php include __DIR__ . '/app/Views/components/dashboard-header.php'; ?>

<div class="content-wrapper">
    <?php 
    // Set active tab for sidebar - classroom is accessed from sidebar
    $active_tab = 'classroom';
    include __DIR__ . '/app/Views/components/dashboard-sidebar.php'; 
    ?>

    <div class="main">
        <div class="container">
        <h1 style="color: #004080;">Learning Materials</h1>
        <p>Access resources, assignments, and videos for your classes.</p>
        
        <?php while($m = $materials->fetch_assoc()): ?>
            <div class="material-card">
                <span class="tag <?php echo $m['type']; ?>"><?php echo strtoupper($m['type']); ?></span>
                <a href="<?php echo htmlspecialchars($m['link_url']); ?>" target="_blank" class="btn-open">Open <i class="fas fa-external-link-alt"></i></a>
                <h3 style="margin: 5px 0; color: #333;"><?php echo htmlspecialchars($m['title']); ?></h3>
                <p style="color: #666; margin: 0;">Posted on <?php echo date('F j, Y', strtotime($m['created_at'])); ?></p>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
