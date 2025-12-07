# Staten Academy

Plataforma de aprendizaje en línea que conecta estudiantes con profesores certificados.

## 🏗️ Arquitectura

Este proyecto sigue los principios de **Clean Architecture** y **Clean Code**:

```
Staten-Academy/
├── public/              # Punto de entrada público
│   ├── index.php        # Router MVC (único entry point)
│   └── assets/          # Recursos estáticos (CSS, JS, imágenes)
├── app/                  # Lógica de aplicación
│   ├── Controllers/     # Controladores MVC
│   ├── Models/          # Modelos de datos
│   ├── Views/           # Vistas y componentes
│   ├── Services/        # Servicios externos (Stripe, Google Calendar)
│   ├── Middleware/      # Autenticación y autorización
│   └── Helpers/         # Helpers reutilizables
├── config/              # Configuración
├── core/                # Framework base
└── api/                 # Endpoints API
```

## 🚀 Inicio Rápido

### Requisitos
- PHP 7.4+
- MySQL 5.7+
- Apache con mod_rewrite

### Instalación

1. **Clonar repositorio**
   ```bash
   git clone [repository-url]
   cd Staten-Academy
   ```

2. **Configurar entorno**
   ```bash
   cp env.example.php env.php
   # Editar env.php con tus credenciales
   ```

3. **Configurar base de datos**
   - La base de datos y tablas se crean automáticamente en el primer acceso
   - O importar `setup-tables.sql` manualmente

4. **Configurar servidor web**
   - Apuntar el DocumentRoot a `public/`
   - O usar el `.htaccess` incluido que redirige automáticamente

## 📁 Estructura de Directorios

### Public (Acceso Público)
- `index.php` - Router MVC, único punto de entrada
- `assets/` - Recursos estáticos (CSS, JS, imágenes)

### App (Lógica de Aplicación)
- **Controllers**: Manejan requests HTTP
- **Models**: Acceso a datos y lógica de negocio
- **Views**: Presentación (componentes, layouts, vistas)
- **Services**: Integraciones externas
- **Middleware**: Control de acceso

### Config (Configuración)
- `app.php` - Configuración de aplicación
- `database.php` - Conexión a base de datos
- `routes.php` - Definición de rutas
- `paths.php` - Rutas centralizadas

## 🔒 Seguridad

- `env.php` nunca se commitea (ver `.gitignore`)
- Directorios sensibles protegidos por `.htaccess`
- Validación de entrada en todos los formularios
- Prepared statements para todas las queries SQL

## 📚 Documentación

Toda la documentación está en `docs/`:
- `REORGANIZATION_COMPLETE.md` - Detalles de la reorganización
- `README_MVC_MIGRATION.md` - Guía de migración MVC
- `DEPLOYMENT_GUIDE.md` - Guía de despliegue

## 🛠️ Desarrollo

### Estructura MVC

```php
// Controller
class UserController extends Controller {
    public function index() {
        $users = $this->userModel->all();
        $this->render('users/index', ['users' => $users]);
    }
}

// Model
class User extends Model {
    protected $table = 'users';
    
    public function findByEmail($email) {
        // Lógica específica
    }
}

// View
// app/Views/users/index.php
```

### Helpers

```php
// PathHelper para rutas
PathHelper::css('styles.css');  // /assets/css/styles.css
PathHelper::image('logo.png');   // /assets/images/logo.png
PathHelper::route('dashboard');  // /dashboard
```

## 🧪 Testing

```bash
# Ejecutar tests
npm test
```

## 📝 Convenciones

- **Nombres**: camelCase para métodos, PascalCase para clases
- **Archivos**: Un archivo por clase
- **Rutas**: RESTful cuando sea posible
- **Código**: PSR-12 coding standard

## 🔄 Migración

El proyecto está en proceso de migración a Clean Architecture:
- ✅ Estructura MVC implementada
- ✅ Assets reorganizados
- ✅ Componentes movidos
- ⏳ Migración gradual de archivos legacy

## 📞 Soporte

Para problemas o preguntas, ver la documentación en `docs/`.

---

**Desarrollado siguiendo principios de Clean Architecture y Clean Code**
