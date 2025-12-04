<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
     header("Location: login.php");
     exit();
 }

$materials = $conn->query("SELECT * FROM classroom_materials ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Classroom - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { display: flex; flex-direction: column; height: 100vh; background: #f4f4f9; margin: 0; }
        .site-header { flex-shrink: 0; }
        .main-wrapper { display: flex; flex: 1; overflow: hidden; }
        .sidebar { 
            width: 250px; 
            background: #2c3e50; 
            color: white; 
            padding-top: 20px; 
            overflow-y: auto;
            flex-shrink: 0;
        }
        .sidebar a { 
            display: block; 
            padding: 15px 20px; 
            color: #adb5bd; 
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar a:hover, .sidebar a.active { 
            background: #34495e; 
            color: white; 
        }
        .sidebar h3 { 
            text-align: center; 
            margin-bottom: 30px; 
            color: white;
            font-size: 1.1rem;
        }
        .sidebar hr {
            border: none;
            border-top: 1px solid #444;
            margin: 15px 0;
        }
        .classroom-content { flex: 1; overflow-y: auto; padding: 40px; }
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
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-left"><a href="index.php"><img src="logo.png" alt="Logo" class="site-logo"></a></div>
        <div class="header-center"><div class="branding"><h1 class="site-title">My Classroom</h1></div></div>
        <?php include 'header-user.php'; ?>
        
        <button id="menu-toggle" class="menu-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
            <span class="hamburger" aria-hidden="true"></span>
        </button>
        
        <div id="mobile-menu" class="mobile-menu" role="menu" aria-hidden="true">
            <button class="close-btn" id="mobile-close" aria-label="Close menu">✕</button>
            <a class="nav-btn" href="index.php">Home</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                    <a class="nav-btn" href="schedule.php">Schedule</a>
                    <a class="nav-btn" href="classroom.php">Classroom</a>
                    <a class="nav-btn" href="profile.php?id=<?php echo $_SESSION['user_id']; ?>">View Profile</a>
                <?php endif; ?>
                <?php if ($user_role === 'student'): ?>
                    <a class="nav-btn" href="schedule.php">Book Lesson</a>
                    <a class="nav-btn" href="student-dashboard.php">My Profile</a>
                <?php endif; ?>
                <?php if ($user_role === 'teacher'): ?>
                    <a class="nav-btn" href="teacher-dashboard.php">Dashboard</a>
                    <a class="nav-btn" href="apply-teacher.php">More Info</a>
                <?php endif; ?>
                <?php if ($user_role === 'admin'): ?>
                    <a class="nav-btn" href="admin-dashboard.php">Admin Panel</a>
                <?php endif; ?>
                <a class="nav-btn" href="message_threads.php">Messages</a>
                <a class="nav-btn" href="support_contact.php">Support</a>
                <a class="nav-btn" href="logout.php">Logout</a>
            <?php endif; ?>
        </div>
    </header>
    <div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>

    <div class="main-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3><?php echo ucfirst($_SESSION['user_role'] ?? 'User'); ?> Portal</h3>
            <?php if ($_SESSION['user_role'] === 'teacher'): ?>
                <a href="teacher-dashboard.php"><i class="fas fa-home"></i> Overview</a>
                <a href="teacher-dashboard.php#profile"><i class="fas fa-user-edit"></i> My Profile</a>
                <a href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a>
                <a href="classroom.php" class="active"><i class="fas fa-book"></i> Classroom</a>
            <?php elseif ($_SESSION['user_role'] === 'student'): ?>
                <a href="student-dashboard.php"><i class="fas fa-home"></i> Overview</a>
                <a href="student-dashboard.php#profile"><i class="fas fa-user-edit"></i> My Profile</a>
                <a href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a>
                <a href="classroom.php" class="active"><i class="fas fa-book"></i> Classroom</a>
            <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                <a href="admin-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="admin-dashboard.php#approvals"><i class="fas fa-check-circle"></i> Approvals</a>
                <a href="admin-dashboard.php#support"><i class="fas fa-headset"></i> Support Messages</a>
                <a href="classroom.php" class="active"><i class="fas fa-book"></i> Classroom</a>
            <?php endif; ?>
            <a href="message_threads.php"><i class="fas fa-comments"></i> Messages</a>
            <a href="support_contact.php"><i class="fas fa-headset"></i> Support</a>
            <hr>
            <a href="index.php"><i class="fas fa-arrow-left"></i> Home Page</a>
            <?php if ($_SESSION['user_role'] === 'teacher'): ?>
                <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>"><i class="fas fa-eye"></i> View Profile</a>
            <?php endif; ?>
            <hr>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="classroom-content">
            <div class="container" style="margin-top: 0;">
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
    <script src="js/menu.js" defer></script>
</body>
</html>
