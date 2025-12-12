<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load dashboard functions if not already loaded
if (!function_exists('getAssetPath')) {
    if (file_exists(__DIR__ . '/app/Views/components/dashboard-functions.php')) {
        require_once __DIR__ . '/app/Views/components/dashboard-functions.php';
    } else {
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
}
$user_profile_pic = null;
$user_name = null;
$user_role = null;
if (isset($_SESSION['user_id'])) {
     // Always fetch fresh profile_pic from DB to ensure accuracy
     if (!isset($conn)) {
         require_once __DIR__ . '/db.php';
     }
     
     // Check if connection exists and is valid
     if (isset($conn) && !$conn->connect_error) {
         $stmt = $conn->prepare("SELECT profile_pic, name, role FROM users WHERE id = ?");
         if ($stmt) {
             $stmt->bind_param("i", $_SESSION['user_id']);
             $stmt->execute();
             $result = $stmt->get_result();
             if ($result && $result->num_rows > 0) {
                 $user = $result->fetch_assoc();
                 $_SESSION['profile_pic'] = $user['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
                 $_SESSION['user_name'] = $user['name'] ?? 'User';
                 $_SESSION['user_role'] = $user['role'] ?? 'guest';
             }
             $stmt->close();
         }
     }
     
     $user_profile_pic = $_SESSION['profile_pic'] ?? getAssetPath('images/placeholder-teacher.svg');
     $user_name = $_SESSION['user_name'] ?? 'User';
     $user_role = $_SESSION['user_role'] ?? 'guest';
}
?>

<style>
    .header-user-section {
        position: absolute;
        right: 90px;
        top: 18px;
        display: flex;
        align-items: center;
        gap: 15px;
        color: #004080;
        z-index: 50;
    }

    .user-info-text {
        text-align: right;
        line-height: 1.2;
    }

    .user-info-text .user-name {
        font-weight: bold;
        color: #004080;
    }

    .user-info-text .user-role {
        font-size: 0.8rem;
        color: rgba(0, 64, 128, 0.7);
        text-transform: capitalize;
    }

    .user-profile-pic {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #004080;
        flex-shrink: 0;
        cursor: pointer;
        transition: transform 0.2s;
        display: block;
    }

    .user-profile-pic:hover {
        transform: scale(1.05);
    }

    .user-menu-dropdown {
        position: absolute;
        right: 0;
        top: 70px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 180px;
        z-index: 1000;
        display: none;
        overflow: hidden;
    }

    .user-menu-dropdown.active {
        display: block;
    }
    
    /* Hide navigation links in dropdown on pages with sidebar - only show Messages, Support, Logout */
    .user-menu-dropdown.has-sidebar a[href="index.php"],
    .user-menu-dropdown.has-sidebar a[href*="schedule.php"],
    .user-menu-dropdown.has-sidebar a[href*="profile.php"],
    .user-menu-dropdown.has-sidebar a[href*="classroom.php"],
    .user-menu-dropdown.has-sidebar a[href*="dashboard.php"],
    .user-menu-dropdown.has-sidebar a[href*="apply-teacher.php"],
    .user-menu-dropdown.has-sidebar a[href*="admin-dashboard.php"],
    .user-menu-dropdown.has-sidebar a[href*="student-dashboard.php"] {
        display: none !important;
    }
    
    .user-menu-dropdown.has-sidebar hr:first-of-type {
        display: none !important;
    }

    .user-menu-dropdown a {
        display: block;
        padding: 12px 15px;
        color: #333;
        text-decoration: none;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }

    .user-menu-dropdown a:last-child {
        border-bottom: none;
    }

    .user-menu-dropdown a:hover {
        background: #f0f0f0;
        color: #0b6cf5;
    }

    .user-menu-dropdown .menu-logout {
        color: #d9534f;
    }

    .user-menu-dropdown .menu-logout:hover {
        background: #ffe6e6;
    }

    /* Responsive header user section */
    @media (max-width: 1024px) {
        .header-user-section {
            right: 70px;
            gap: 12px;
        }
        
        .user-info-text {
            display: none; /* Hide text on tablet, show only picture */
        }
        
        .user-profile-pic {
            width: 40px;
            height: 40px;
        }
    }
    
    @media (max-width: 768px) {
        .header-user-section {
            right: 50px;
            top: 14px;
            gap: 8px;
        }
        
        .user-profile-pic {
            width: 36px;
            height: 36px;
            border-width: 1.5px;
        }
        
        .user-menu-dropdown {
            top: 50px;
            right: -10px;
            min-width: 160px;
            font-size: 0.9rem;
        }
        
        .user-menu-dropdown a {
            padding: 10px 12px;
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 576px) {
        .header-user-section {
            right: 40px;
            top: 12px;
        }
        
        .user-profile-pic {
            width: 32px;
            height: 32px;
        }
        
        .user-menu-dropdown {
            top: 45px;
            right: -20px;
            min-width: 150px;
        }
    }
    
    @media (max-width: 480px) {
        .header-user-section {
            position: relative;
            right: auto;
            top: auto;
            margin-top: 8px;
            justify-content: center;
        }
    }
</style>

<?php if (isset($_SESSION['user_id'])): ?>
<?php 
// Check if current page has a sidebar
$has_sidebar = false;
$current_page = basename($_SERVER['PHP_SELF']);
$sidebar_pages = ['teacher-dashboard.php', 'student-dashboard.php', 'admin-dashboard.php', 'classroom.php', 'schedule.php'];
if (in_array($current_page, $sidebar_pages)) {
    $has_sidebar = true;
}
?>
<div class="header-user-section">
    <div class="user-info-text">
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
    </div>
    <img 
        src="<?php echo htmlspecialchars($user_profile_pic); ?>" 
        alt="Profile" 
        class="user-profile-pic"
        onclick="toggleUserMenu()"
        onerror="this.src='<?php echo getAssetPath('images/placeholder-teacher.svg'); ?>'"
    >
    <div class="user-menu-dropdown <?php echo $has_sidebar ? 'has-sidebar' : ''; ?>" id="userMenuDropdown">
        <?php 
        // On pages with sidebar, only show essential links (Messages, Support, Logout)
        // On pages without sidebar, show all navigation links
        if (!$has_sidebar):
        ?>
            <a href="index.php">Home</a>
            <a href="about.php">About Us</a>
            <a href="how-we-work.php">How We Work</a>
            <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                <a href="schedule.php">Schedule</a>
                <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>">View Profile</a>
                <a href="classroom.php">Classroom</a>
            <?php endif; ?>
            <?php if ($user_role === 'visitor'): ?>
                <a href="visitor-dashboard.php">My Dashboard</a>
                <a href="payment.php">Upgrade to Student</a>
            <?php elseif ($user_role === 'student'): ?>
                <a href="schedule.php">Book Lesson</a>
                <a href="student-dashboard.php">My Profile</a>
            <?php endif; ?>
            <?php if ($user_role === 'teacher'): ?>
                <a href="teacher-dashboard.php">Dashboard</a>
                <a href="apply-teacher.php">More Info</a>
            <?php endif; ?>
            <?php if ($user_role === 'admin'): ?>
                <a href="admin-dashboard.php">Admin Panel</a>
            <?php endif; ?>
            <hr style="margin: 5px 0; border: none; border-top: 1px solid #eee;">
        <?php endif; ?>
        <!-- Always show these essential links -->
        <a href="message_threads.php">Messages</a>
        <a href="support_contact.php">Support</a>
        <hr style="margin: 5px 0; border: none; border-top: 1px solid #eee;">
        <a href="logout.php" class="menu-logout">Logout</a>
    </div>
</div>

<script>
    function toggleUserMenu() {
        const dropdown = document.getElementById('userMenuDropdown');
        dropdown.classList.toggle('active');
    }
    document.addEventListener('click', function(event) {
        const userSection = document.querySelector('.header-user-section');
        const dropdown = document.getElementById('userMenuDropdown');
        if (userSection && !userSection.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });
</script>
<?php endif; ?>
