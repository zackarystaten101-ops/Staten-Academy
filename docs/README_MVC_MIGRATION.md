# MVC Architecture Migration

This project has been refactored to use a clean MVC (Model-View-Controller) architecture. The new structure is in place, but the migration is gradual to maintain functionality.

## New Directory Structure

```
Staten-Academy/
├── app/
│   ├── Controllers/     # Request handlers
│   ├── Models/          # Data models and business logic
│   ├── Views/           # Presentation layer
│   ├── Services/        # External integrations (Stripe, Google Calendar)
│   └── Middleware/      # Access control
├── config/              # Configuration files
├── core/                # Core framework classes
└── public/              # Public entry point
```

## Current Status

### ✅ Completed
- Core infrastructure (Router, Controller, Model, View base classes)
- Configuration system (database.php, app.php)
- All Model classes (User, Booking, Lesson, Message, Material, etc.)
- All Service classes (AuthService, CalendarService, PaymentService, NotificationService)
- All Controller classes (AuthController, DashboardController, TeacherController, etc.)
- Middleware system (AuthMiddleware, RoleMiddleware, AdminMiddleware)
- Routing system with middleware support
- Public entry point (public/index.php)

### 🔄 In Progress
- View migration (views are being migrated gradually)
- API endpoint refactoring

### 📋 To Do
- Complete view migration
- Update all file paths and includes
- Test all functionality
- Remove old files

## Usage

### For Development
The old files are still functional. The new MVC structure is available but not yet fully integrated.

### Routing
Routes are defined in `config/routes.php`. Example:
```php
$router->get('/dashboard/teacher', 'TeacherController@dashboard', ['AuthMiddleware', 'RoleMiddleware:teacher']);
```

### Controllers
Controllers extend the base `Controller` class and use Models and Services:
```php
class TeacherController extends Controller {
    public function dashboard() {
        $this->requireAuth();
        $this->render('dashboard/teacher/index', ['data' => $data]);
    }
}
```

### Models
Models extend the base `Model` class:
```php
class User extends Model {
    protected $table = 'users';
    
    public function findByEmail($email) {
        // Custom query logic
    }
}
```

## Migration Strategy

1. **Gradual Migration**: Old files remain functional while new structure is built
2. **Backward Compatibility**: Views can reference old files during transition
3. **Testing**: Each component is tested before full migration
4. **Cleanup**: Old files will be removed once migration is complete

## Next Steps

1. Complete view migration for all pages
2. Update API endpoints to use Controllers
3. Update all includes/requires to use new paths
4. Test all functionality
5. Remove old files

