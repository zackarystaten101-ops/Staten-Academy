<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - ' : ''; ?>Staten Academy</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="/css/mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-layout">
    <?php 
    // Include dashboard header and sidebar
    if (file_exists(__DIR__ . '/../components/dashboard-header.php')) {
        include __DIR__ . '/../components/dashboard-header.php';
    }
    if (file_exists(__DIR__ . '/../components/dashboard-sidebar.php')) {
        include __DIR__ . '/../components/dashboard-sidebar.php';
    }
    ?>
    
    <div class="content-wrapper">
        <main class="main-content">
            <?php echo $content; ?>
        </main>
    </div>
    
    <script src="/js/menu.js"></script>
</body>
</html>

