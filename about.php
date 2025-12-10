<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Views/components/dashboard-functions.php';

$page_title = "About Us - Staten Academy";
$page_description = "Learn about Staten Academy, our mission, values, and why we're the best choice for English learning.";

// Set user if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = getUserById($conn, $_SESSION['user_id']);
}

include __DIR__ . '/app/Views/components/header-user.php';
?>

<main style="padding-top: 80px;">
    <!-- Hero Section -->
    <section style="background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); color: white; padding: 80px 20px; text-align: center;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h1 style="font-size: 3rem; margin-bottom: 20px; font-weight: 700;">About Staten Academy</h1>
            <p style="font-size: 1.3rem; max-width: 800px; margin: 0 auto; line-height: 1.8;">
                Empowering learners worldwide to achieve their English language goals through personalized, 
                interactive, and engaging online education.
            </p>
        </div>
    </section>

    <!-- Mission Section -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 50px;">
                <h2 style="font-size: 2.5rem; color: #004080; margin-bottom: 15px;">Our Mission</h2>
                <p style="font-size: 1.2rem; color: #666; max-width: 900px; margin: 0 auto;">
                    To provide high-quality, accessible English language education that adapts to each learner's 
                    unique needs, learning style, and goals. We believe that language learning should be engaging, 
                    personalized, and effective.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 50px;">
                <div style="text-align: center; padding: 30px; border-radius: 12px; background: #f8f9fa;">
                    <div style="font-size: 3rem; margin-bottom: 20px; color: #0b6cf5;">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 15px;">Passion for Education</h3>
                    <p style="color: #666; line-height: 1.7;">
                        We're passionate about helping students achieve their language learning goals through innovative teaching methods.
                    </p>
                </div>

                <div style="text-align: center; padding: 30px; border-radius: 12px; background: #f8f9fa;">
                    <div style="font-size: 3rem; margin-bottom: 20px; color: #0b6cf5;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 15px;">Student-Centered</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Every student is unique. We tailor our approach to match individual learning styles, preferences, and objectives.
                    </p>
                </div>

                <div style="text-align: center; padding: 30px; border-radius: 12px; background: #f8f9fa;">
                    <div style="font-size: 3rem; margin-bottom: 20px; color: #0b6cf5;">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 15px;">Global Reach</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Connecting students and teachers from around the world to create meaningful learning experiences.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section style="padding: 60px 20px; background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 50px;">Why Choose Staten Academy?</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">
                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #0b6cf5;">
                    <div style="font-size: 2rem; color: #0b6cf5; margin-bottom: 15px;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Expert Teachers</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Our teachers are experienced, certified, and passionate about helping you succeed. 
                        They're carefully selected and continuously trained.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
                    <div style="font-size: 2rem; color: #28a745; margin-bottom: 15px;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Flexible Scheduling</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Learn on your schedule. Book classes at times that work for you, whether it's early morning, 
                        late evening, or weekends.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #ffc107;">
                    <div style="font-size: 2rem; color: #ffc107; margin-bottom: 15px;">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Interactive Platform</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Our modern platform makes learning engaging with interactive materials, real-time collaboration, 
                        and progress tracking.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #17a2b8;">
                    <div style="font-size: 2rem; color: #17a2b8; margin-bottom: 15px;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Progress Tracking</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Monitor your improvement with detailed progress reports, assignments, and personalized feedback 
                        from your teacher.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;">
                    <div style="font-size: 2rem; color: #dc3545; margin-bottom: 15px;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Affordable Plans</h3>
                    <p style="color: #666; line-height: 1.7;">
                        We offer flexible pricing plans to fit every budget, from single classes to monthly subscriptions 
                        with group and one-on-one options.
                    </p>
                </div>

                <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-left: 4px solid #6f42c1;">
                    <div style="font-size: 2rem; color: #6f42c1; margin-bottom: 15px;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 style="color: #004080; margin-bottom: 10px;">Diverse Learning Tracks</h3>
                    <p style="color: #666; line-height: 1.7;">
                        Choose from Kids Classes, Adult General English, or English for Coding. 
                        Each track is tailored to specific needs and goals.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; color: #004080; text-align: center; margin-bottom: 50px;">Our Values</h2>
            
            <div style="max-width: 900px; margin: 0 auto;">
                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #0b6cf5;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #0b6cf5;"></i>
                        Excellence
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We strive for excellence in everything we do, from teacher selection to curriculum design 
                        and student support.
                    </p>
                </div>

                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #28a745;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        Integrity
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We conduct our business with honesty, transparency, and respect for all students, teachers, 
                        and partners.
                    </p>
                </div>

                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #ffc107;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #ffc107;"></i>
                        Innovation
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We continuously improve our platform and teaching methods to provide the best possible 
                        learning experience.
                    </p>
                </div>

                <div style="margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 5px solid #17a2b8;">
                    <h3 style="color: #004080; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #17a2b8;"></i>
                        Support
                    </h3>
                    <p style="color: #666; line-height: 1.8; margin: 0;">
                        We're committed to supporting our students every step of the way, from enrollment to 
                        achieving their language goals.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section style="padding: 60px 20px; background: linear-gradient(135deg, #0b6cf5 0%, #004080 100%); color: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto;">
            <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 50px;">What Our Students Say</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <div style="background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                    <p style="font-style: italic; line-height: 1.8; margin-bottom: 20px;">
                        "Staten Academy has transformed my English skills! The teachers are patient and 
                        the platform is easy to use. Highly recommended!"
                    </p>
                    <p style="font-weight: 600;">— Sarah M., Adult Student</p>
                </div>

                <div style="background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                    <p style="font-style: italic; line-height: 1.8; margin-bottom: 20px;">
                        "My 8-year-old loves the kids classes! The interactive materials keep him engaged 
                        and he's learning so much faster than in traditional classes."
                    </p>
                    <p style="font-weight: 600;">— Maria L., Parent</p>
                </div>

                <div style="background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px);">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                    </div>
                    <p style="font-style: italic; line-height: 1.8; margin-bottom: 20px;">
                        "The English for Coding track helped me improve my technical communication skills. 
                        Perfect for developers looking to enhance their English!"
                    </p>
                    <p style="font-weight: 600;">— John D., Coding Student</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section style="padding: 60px 20px; background: white;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <h2 style="font-size: 2.5rem; color: #004080; margin-bottom: 30px;">Get in Touch</h2>
            <p style="font-size: 1.2rem; color: #666; margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto;">
                Have questions? We're here to help! Reach out to us through our support system or check out 
                our <a href="how-we-work.php" style="color: #0b6cf5; text-decoration: underline;">How We Work</a> page.
            </p>
            <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
                <a href="support_contact.php" class="btn-primary" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block;">
                    <i class="fas fa-envelope"></i> Contact Support
                </a>
                <a href="index.php" class="btn-outline" style="padding: 15px 40px; font-size: 1.1rem; text-decoration: none; display: inline-block;">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/app/Views/components/footer.php'; ?>

