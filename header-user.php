<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_profile_pic = null;
$user_name = null;
$user_role = null;
if (isset($_SESSION['user_id'])) {
     // Always fetch fresh profile_pic from DB to ensure accuracy
     require_once 'db.php';
     $stmt = $conn->prepare("SELECT profile_pic, name, role FROM users WHERE id = ?");
     $stmt->bind_param("i", $_SESSION['user_id']);
     $stmt->execute();
     $result = $stmt->get_result();
     if ($result->num_rows > 0) {
         $user = $result->fetch_assoc();
         $_SESSION['profile_pic'] = $user['profile_pic'] ?? 'images/placeholder-teacher.svg';
         $_SESSION['user_name'] = $user['name'];
         $_SESSION['user_role'] = $user['role'];
     }
     $stmt->close();
     
     $user_profile_pic = $_SESSION['profile_pic'] ?? 'images/placeholder-teacher.svg';
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
        color: white;
        z-index: 50;
    }

    .user-info-text {
        text-align: right;
        line-height: 1.2;
    }

    .user-info-text .user-name {
        font-weight: bold;
        color: white;
    }

    .user-info-text .user-role {
        font-size: 0.8rem;
        color: #ccc;
        text-transform: capitalize;
    }

    .user-profile-pic {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid white;
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

    @media (max-width: 900px) {
        .header-user-section {
            display: none;
        }
    }
</style>

<?php if (isset($_SESSION['user_id'])): ?>
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
        onerror="this.src='images/placeholder-teacher.svg'"
    >
    <div class="user-menu-dropdown" id="userMenuDropdown">
        <a href="index.php">Home</a>
        <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
            <a href="schedule.php">Schedule</a>
            <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>">View Profile</a>
            <a href="classroom.php">Classroom</a>
        <?php endif; ?>
        <?php if ($user_role === 'student'): ?>
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
