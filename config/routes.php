<?php
/**
 * Application Routes
 */

require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../app/Middleware/AdminMiddleware.php';

$router = new Router();

// Public routes
$router->get('/', function() {
    // For now, redirect to old index.php until full migration
    if (file_exists(__DIR__ . '/../index.php')) {
        header("Location: /index.php");
    } else {
        echo "Welcome to Staten Academy";
    }
    exit;
});

// Auth routes
$router->get('/auth/login', 'AuthController@login');
$router->post('/auth/login', 'AuthController@login');
$router->get('/auth/register', 'AuthController@register');
$router->post('/auth/register', 'AuthController@register');
$router->get('/auth/logout', 'AuthController@logout');

// Dashboard routes (require auth)
$router->get('/dashboard', 'DashboardController@index', ['AuthMiddleware']);
$router->get('/dashboard/teacher', 'TeacherController@dashboard', ['AuthMiddleware', 'RoleMiddleware:teacher']);
$router->get('/dashboard/student', 'StudentController@dashboard', ['AuthMiddleware', 'RoleMiddleware:student']);
$router->get('/dashboard/admin', 'AdminController@dashboard', ['AuthMiddleware', 'RoleMiddleware:admin']);

// Profile routes
$router->get('/profile/view/{id}', 'ProfileController@view');

// Schedule routes
$router->get('/schedule', 'ScheduleController@index', ['AuthMiddleware']);
$router->post('/schedule/book', 'ScheduleController@book', ['AuthMiddleware']);

// Message routes
$router->get('/messages', 'MessageController@threads', ['AuthMiddleware']);
$router->post('/messages/send', 'MessageController@send', ['AuthMiddleware']);

// Material routes
$router->get('/materials', 'MaterialController@index', ['AuthMiddleware']);
$router->post('/materials/upload', 'MaterialController@upload', ['AuthMiddleware']);
$router->get('/materials/delete/{id}', 'MaterialController@delete', ['AuthMiddleware']);

// Payment routes
$router->get('/payment', 'PaymentController@index', ['AuthMiddleware']);
$router->post('/payment/checkout', 'PaymentController@checkout', ['AuthMiddleware']);

// Support routes
$router->get('/support/contact', 'SupportController@contact', ['AuthMiddleware']);
$router->post('/support/contact', 'SupportController@contact', ['AuthMiddleware']);

return $router;

