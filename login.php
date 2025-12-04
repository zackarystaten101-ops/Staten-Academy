<?php
// Load environment configuration first
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/env.php';
}

// Enable error reporting based on APP_DEBUG
if (defined('APP_DEBUG') && APP_DEBUG === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database connection
require_once __DIR__ . '/db.php';

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check your database configuration.");
}

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Standard Login
    if (isset($_POST['email'])) {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $login_error = "Please enter both email and password.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $name, $hashed_password, $role);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                     // Fetch full user data including profile_pic
                     $full_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
                     $full_stmt->bind_param("i", $id);
                     $full_stmt->execute();
                     $full_result = $full_stmt->get_result();
                     $full_user = $full_result->fetch_assoc();
                     $full_stmt->close();
                     
                     $_SESSION['user_id'] = $id;
                     $_SESSION['user_name'] = $name;
                     $_SESSION['user_role'] = $role;
                     $_SESSION['profile_pic'] = $full_user['profile_pic'] ?? 'images/placeholder-teacher.svg';

                    // Redirect Logic
                    if ($role === 'admin') {
                        // Admins ALWAYS go to dashboard, ignore booking redirects
                        if (isset($_SESSION['redirect_teacher'])) unset($_SESSION['redirect_teacher']);
                        header("Location: admin-dashboard.php");
                        exit();
                    } 
                    else if ($role === 'teacher') {
                        // Teachers ALWAYS go to dashboard
                        if (isset($_SESSION['redirect_teacher'])) unset($_SESSION['redirect_teacher']);
                        header("Location: teacher-dashboard.php");
                        exit();
                    } 
                    else {
                        // Students: Check if they were trying to book a specific teacher
                        $_SESSION['user_role'] = 'student';
                        if (isset($_SESSION['redirect_teacher'])) {
                            $teacher = $_SESSION['redirect_teacher'];
                            unset($_SESSION['redirect_teacher']);
                            header("Location: schedule.php?teacher=" . urlencode($teacher));
                        } else {
                            // Default landing page for students logging in from Home Page
                            header("Location: student-dashboard.php"); 
                        }
                        exit();
                    }
                } else {
                    $login_error = "Invalid password. Please try again.";
                }
            } else {
                $login_error = "No account found with that email. <a href='register.php'>Sign up here</a>";
            }
            $stmt->close();
        }
    } 
    // Handle Google Login (AJAX usually sends this)
    else if (isset($_POST['google_token'])) {
        // Verification logic...
        
        $email = $_POST['google_email'];
        $name = $_POST['google_name'];
        $google_id = $_POST['google_id'];

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
             // Login existing
             $stmt->bind_result($id, $role);
             $stmt->fetch();
             $stmt->close();
             
             // Fetch profile_pic for session
             $pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
             $pic_stmt->bind_param("i", $id);
             $pic_stmt->execute();
             $pic_result = $pic_stmt->get_result();
             $pic_user = $pic_result->fetch_assoc();
             $pic_stmt->close();
             
             $_SESSION['user_id'] = $id;
             $_SESSION['user_name'] = $name;
             $_SESSION['user_role'] = $role;
             $_SESSION['profile_pic'] = $pic_user['profile_pic'] ?? 'images/placeholder-teacher.svg';
        } else {
            // Register new Google user
            $stmt->close();
            
            $new_role = 'student';

            $stmt = $conn->prepare("INSERT INTO users (name, email, google_id, role, profile_pic) VALUES (?, ?, ?, ?, ?)");
            $default_pic = 'images/placeholder-teacher.svg';
            $stmt->bind_param("sssss", $name, $email, $google_id, $new_role, $default_pic);
            if($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $new_role;
                $_SESSION['profile_pic'] = $default_pic;
            }
        }

        echo "success";
        exit();
    }
}

// If there's an error or not a POST request, show login form with error
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Staten Academy</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/mobile.css">
    <link rel="stylesheet" href="css/auth.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <header class="site-header">
        <div class="header-left"><a href="index.php"><img src="logo.png" alt="Logo" class="site-logo"></a></div>
    </header>

    <div class="auth-container">
        <h2>Welcome Back</h2>
        
        <?php if ($login_error): ?>
            <div class="error-message">
                <?php echo $login_error; ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">Log In</button>
        </form>

        <div class="or-divider">OR</div>

        <!-- Google Login Button -->
        <div id="g_id_onload"
             data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
             data-callback="handleCredentialResponse">
        </div>
        <div class="g_id_signin" data-type="standard"></div>

        <p style="margin-top: 20px;">Don't have an account? <a href="register.php">Sign Up</a></p>
    </div>

    <script>
        function handleCredentialResponse(response) {
            // Decode JWT to get user info (simplified for demo)
            const responsePayload = decodeJwtResponse(response.credential);
            
            // Send to server
            const formData = new FormData();
            formData.append('google_token', response.credential);
            formData.append('google_email', responsePayload.email);
            formData.append('google_name', responsePayload.name);
            formData.append('google_id', responsePayload.sub);

            fetch('login.php', {
                method: 'POST',
                body: formData
            }).then(res => res.text()).then(data => {
                if (data.includes('success')) {
                    // Determine redirect based on role (will be handled by server-side redirects)
                    window.location.href = 'schedule.php';
                } else {
                    alert('Login failed: ' + data);
                }
            }).catch(err => {
                console.error('Login error:', err);
                alert('An error occurred during login');
            });
        }

        function decodeJwtResponse(token) {
            var base64Url = token.split('.')[1];
            var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        }
    </script>
</body>
</html>
