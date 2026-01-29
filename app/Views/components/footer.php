    <footer style="background: #004080; color: white; padding: 40px 20px; text-align: center;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px; margin-bottom: 30px; text-align: left;">
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Staten Academy</h3>
                    <p style="color: rgba(255,255,255,0.8); line-height: 1.8; font-size: 0.95rem;">
                        Empowering learners worldwide to achieve their English language goals.
                    </p>
                </div>
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Quick Links</h3>
                    <ul style="list-style: none; padding: 0; line-height: 2;">
                        <li><a href="index.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Home</a></li>
                        <li><a href="about.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">About Us</a></li>
                        <li><a href="how-we-work.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">How We Work</a></li>
                        <li><a href="kids-plans.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Group Classes</a></li>
                    </ul>
                </div>
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Our Classes</h3>
                    <ul style="list-style: none; padding: 0; line-height: 2;">
                        <li><a href="kids-plans.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Group Classes</a></li>
                        <li style="color: rgba(255,255,255,0.6);">One-on-One Classes (Coming Soon)</li>
                    </ul>
                </div>
                <div>
                    <h3 style="margin-bottom: 15px; color: white;">Support</h3>
                    <ul style="list-style: none; padding: 0; line-height: 2;">
                        <li><a href="support_contact.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Contact Support</a></li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li><a href="login.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Login</a></li>
                            <li><a href="register.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.2); margin: 30px 0;">
            <p style="color: rgba(255,255,255,0.8); margin: 0;">
                &copy; <?php echo date('Y'); ?> Staten Academy. All rights reserved.
            </p>
        </div>
    </footer>
    <?php
    // Ensure getAssetPath function is available
    if (!function_exists('getAssetPath')) {
        if (file_exists(__DIR__ . '/dashboard-functions.php')) {
            require_once __DIR__ . '/dashboard-functions.php';
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
    ?>
    <script src="<?php echo getAssetPath('js/menu.js'); ?>" defer></script>
</body>
</html>

