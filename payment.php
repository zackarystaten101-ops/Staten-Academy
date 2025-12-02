<?php
session_start();
$user_role = $_SESSION['user_role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plans & Pricing - Staten Academy</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="css/mobile.css">
  <style>
    .payment-header {
        text-align: center;
        padding: 60px 20px;
        background: #004080;
        color: white;
    }
    .payment-header h1 { margin: 0; font-size: 2.5rem; }
    .payment-header p { margin-top: 10px; opacity: 0.9; }
    
    .pricing-container {
        max-width: 1000px;
        margin: -40px auto 60px;
        padding: 0 20px;
        position: relative;
        z-index: 10;
    }

    .single-class-box {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        padding: 30px;
        text-align: center;
        margin-bottom: 40px;
        border: 2px solid #0b6cf5;
    }
    .single-class-box h2 { color: #004080; margin-bottom: 10px; }
    .single-class-price { font-size: 2.5rem; color: #0b6cf5; font-weight: bold; }
    
    .btn-buy {
        display: inline-block;
        background: #0b6cf5;
        color: white;
        padding: 15px 40px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: bold;
        font-size: 1.1rem;
        border: none;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .btn-buy:hover { transform: scale(1.05); background: #0056b3; }

    .plan-form { height: 100%; }
    .plan-button {
        width: 100%;
        height: 100%;
        background: none;
        border: none;
        text-align: left;
        padding: 0;
        cursor: pointer;
    }
  </style>
</head>
<body>

  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php"><img src="logo.png" alt="Staten Academy logo" class="site-logo"></a>
    </div>
    <div class="header-center">
        <div class="branding">
          <h1 class="site-title">Staten Academy</h1>
        </div>
    </div>
    
    <?php include 'header-user.php'; ?>
    
    <button id="menu-toggle" class="menu-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
        <span class="hamburger" aria-hidden="true"></span>
    </button>
    <div id="mobile-menu" class="mobile-menu" role="menu" aria-hidden="true">
        <button class="close-btn" id="mobile-close" aria-label="Close menu">✕</button>
        <a class="nav-btn" href="index.php">Home</a>
        <a class="nav-btn" href="index.php#teachers">Teachers</a>
        <a class="nav-btn" href="index.php#about">About Us</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($user_role === 'student'): ?>
                <a class="nav-btn" href="student-dashboard.php">My Dashboard</a>
            <?php elseif ($user_role === 'teacher'): ?>
                <a class="nav-btn" href="teacher-dashboard.php">Dashboard</a>
            <?php elseif ($user_role === 'admin'): ?>
                <a class="nav-btn" href="admin-dashboard.php">Admin Panel</a>
            <?php endif; ?>
            <a class="nav-btn" href="logout.php">Logout</a>
        <?php else: ?>
            <a class="nav-btn" href="login.php">Login / Sign Up</a>
        <?php endif; ?>
    </div>
  </header>
  <div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>

  <div class="payment-header">
      <h1>Choose Your Plan</h1>
      <p>Invest in your future with flexible English learning options.</p>
  </div>

  <div class="pricing-container">
      
      <div class="single-class-box">
          <h2>Single Class / Trial Lesson</h2>
          <p>Perfect for trying out a teacher or flexible scheduling.</p>
          <div class="single-class-price">$30 <span style="font-size: 1rem; color: #666; font-weight: normal;">/ hour</span></div>
          <div style="margin: 20px 0;">
              <form action="create_checkout_session.php" method="POST">
                  <input type="hidden" name="price_id" value="price_1SXv22Fg7Fwmuz0xYimW2nGp">
                  <input type="hidden" name="mode" value="payment">
                  <button type="submit" class="btn-buy">Book Now</button>
              </form>
          </div>
      </div>

      <h2 style="text-align: center; margin-bottom: 30px; color: #004080;">Monthly Subscriptions</h2>
      
      <div class="plans-grid">
          
        <div class="plan">
            <form action="create_checkout_session.php" method="POST" class="plan-form">
                <input type="hidden" name="price_id" value="price_1SXvP8Fg7Fwmuz0x0bCZPbp2">
                <input type="hidden" name="mode" value="subscription"> 
                <button type="submit" class="plan-button">
                    <div class="plan-body">
                        <h3>Economy Plan</h3>
                        <p class="desc">1 class per week with a certified teacher.</p>
                        <p class="price">$85 / month</p>
                    </div>
                </button>
            </form>
        </div>

        <div class="plan">
            <form action="create_checkout_session.php" method="POST" class="plan-form">
                <input type="hidden" name="price_id" value="price_BASIC_PLACEHOLDER">
                <input type="hidden" name="mode" value="subscription">
                <button type="submit" class="plan-button">
                    <div class="plan-body">
                        <h3>Basic Plan</h3>
                        <p class="desc">2 classes per week. Choose your own tutor.</p>
                        <p class="price">$240 / month</p>
                    </div>
                </button>
            </form>
        </div>

        <div class="plan">
            <form action="create_checkout_session.php" method="POST" class="plan-form">
                <input type="hidden" name="price_id" value="price_STANDARD_PLACEHOLDER">
                <input type="hidden" name="mode" value="subscription">
                <button type="submit" class="plan-button">
                    <div class="plan-body">
                        <h3>Standard Plan</h3>
                        <p class="desc">4 classes per week, extra learning resources.</p>
                        <p class="price">$400 / month</p>
                    </div>
                </button>
            </form>
        </div>

        <div class="plan">
            <form action="create_checkout_session.php" method="POST" class="plan-form">
                <input type="hidden" name="price_id" value="price_PREMIUM_PLACEHOLDER">
                <input type="hidden" name="mode" value="subscription">
                <button type="submit" class="plan-button">
                    <div class="plan-body">
                        <h3>Premium Plan</h3>
                        <p class="desc">Unlimited classes, exclusive materials.</p>
                        <p class="price">$850 / month</p>
                    </div>
                </button>
            </form>
        </div>

      </div>
      <p style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">* Plans renew automatically. Cancel anytime.</p>
  </div>

  <footer>
    <p>Contact us: info@statenacademy.com | Phone: +1 234 567 890</p>
    <p>&copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.</p>
  </footer>
  <script src="js/menu.js" defer></script>
</body>
</html>

