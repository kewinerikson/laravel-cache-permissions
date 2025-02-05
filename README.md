# Sistema de Roles y Permisos en Laravel con Caché Global

## Descripción General
Sistema de gestión de roles y permisos con implementación eficiente de caché para optimizar rendimiento y control de acceso.

## Características Principales
- Caché global de roles y permisos
- Sistema de versionado de permisos
- Control de acceso mediante Gates y Blade directives
- Invalidación dinámica de caché

## Instalación y Configuración

### Paso 1: Migraciones Requeridas
```bash
php artisan make:migration create_roles_table
php artisan make:migration create_permissions_table
php artisan make:migration create_role_user_table
php artisan make:migration create_permission_user_table
php artisan make:migration create_permission_role_table
```
### Paso 2: creacion de archivos 
php artisan make:provider AuthServiceProvider
php artisan make:middleware RoleMiddleware
### Paso 2: Modelos Requeridos
User
Role
Permission
#### Modelo Role
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model 
{
    protected $fillable = ['name', 'slug'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
```

#### Modelo Permission
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model 
{
    protected $fillable = ['name', 'slug'];
    

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
```

### Modificaciones en User Model
Se deben agregar métodos de gestión de roles y permisos en el modelo User.
```php
    public const PERMISSIONS_VERSION_KEY = 'permissions_version'; // Versión de permisos
    public const GLOBAL_ROLES_KEY = 'global_roles_permissions'; // Clave global de roles
    private const CACHE_TTL = 1440; // Tiempo de vida en minutos (24 horas)
    private const CACHE_PREFIX = 'user_roles_permissions_'; // Prefijo para claves de caché por usuario

       /**
     * Evento de inicialización del modelo.
     * Se ejecuta cuando se guarda o elimina un usuario para actualizar la versión de permisos.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Evento cuando se guarda un usuario (crear o actualizar)
        static::saved(function ($user) {
            self::incrementPermissionsVersion();
            self::logUserChange($user, 'saved');
        });

        // Evento cuando se elimina un usuario
        static::deleted(function ($user) {
            self::incrementPermissionsVersion();
            self::logUserChange($user, 'deleted');
        });
    }

    /**
     * Relación muchos a muchos con roles.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Relación muchos a muchos con permisos.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

        /**
     * Asigna un rol al usuario.
     */
    public function assignRole($roleId)
    {
        DB::transaction(function () use ($roleId) {
            $this->roles()->attach($roleId);
            self::incrementPermissionsVersion();
        });
    }

    /**
     * Elimina un rol del usuario.
     */
    public function removeRole($roleId)
    {
        DB::transaction(function () use ($roleId) {
            $this->roles()->detach($roleId);
            self::incrementPermissionsVersion();
        });
    }

    /**
     * Reemplaza todos los roles del usuario con uno nuevo.
     */
    public function changeRole($newRoleId)
    {
        DB::transaction(function () use ($newRoleId) {
            $this->roles()->sync([$newRoleId]);
            self::incrementPermissionsVersion();
        });
    }

    /**
     * Obtiene los roles globales con permisos, almacenándolos en caché.
     */
    private static function getGlobalRoles()
    {
        return Cache::remember(self::GLOBAL_ROLES_KEY, self::CACHE_TTL, function () {
            return Role::with('permissions:id,name')
                ->get(['id', 'name'])
                ->mapWithKeys(function ($role) {
                    return [
                        $role->name => [
                            'id' => $role->id,
                            'permissions' => $role->permissions->pluck('name')->toArray()
                        ]
                    ];
                })->toArray();
        });
    }

        /**
     * Verifica si el usuario tiene al menos uno de los roles especificados.
     */
    public function hasAnyRole($roles)
    {
        if (is_string($roles)) {
            $roles = explode(',', $roles);
        }
        
        $roles = array_map(function($role) {
            return trim($role, "'\" ");
        }, $roles);

        $userData = $this->getUserPermissionsAndRole();
        return in_array(strtolower($userData['role']), array_map('strtolower', $roles));
    }

    /**
     * Verifica si el usuario tiene un permiso específico.
     */
    public function hasPermission($permissionName)
    {
        $userData = $this->getUserPermissionsAndRole();
        return in_array($permissionName, $userData['permissions']);
    }

    /**
     * Verifica si el usuario tiene al menos uno de los permisos especificados.
     */
    public function hasAnyPermission($permissions)
    {
        $userData = $this->getUserPermissionsAndRole();
        
        $permissionsArray = is_string($permissions) ? explode(',', $permissions) : $permissions;
        
        return !empty(array_intersect($userData['permissions'], $permissionsArray));
    }

    / ==============================
    // GESTIÓN DE CACHÉ MEJORADA
    // ==============================

    /**
     * Incrementa la versión de los permisos y limpia la caché global.
     */
    private static function incrementPermissionsVersion()
    {
        Cache::increment(self::PERMISSIONS_VERSION_KEY);
        Cache::forget(self::GLOBAL_ROLES_KEY);
    }

    /**
     * Obtiene el rol y permisos del usuario, almacenando los datos en caché.
     */
    public function getUserPermissionsAndRole()
    {
        $version = Cache::get(self::PERMISSIONS_VERSION_KEY, 1);
        $cacheKey = self::CACHE_PREFIX . "{$this->id}_{$version}";
        $globalRoles = self::getGlobalRoles();

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($globalRoles) {
            $role = $this->roles()->select('name')->first();
            $roleName = $role ? $role->name : null;
            
            return [
                'role' => $roleName,
                'permissions' => $roleName ? ($globalRoles[$roleName]['permissions'] ?? []) : [],
                'cached_at' => now()->timestamp,
                'version' => Cache::get(self::PERMISSIONS_VERSION_KEY)
            ];
        });
    }
```
### Agregar al kernel
    app\Http\Kernel.php
```php 
  protected $routeMiddleware = [
        // Otros middlewares...
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ];
```
## Ejemplos de Uso

### ejemplo controlador de roles y permisos 
    app\Http\Controllers\RolePermissionController.php

### Rutas (web.php)

#### Validación por Rol Específico
```php
// Solo acceso para usuarios con rol 'admin'
Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])
    ->name('roles.permissions.edit')
    ->middleware('role:admin');

// Validación por Múltiples Roles
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware('role:admin,user');
```

#### Validación por Permiso
```php
// Requiere permiso específico
Route::get('/users', [UserController::class, 'index'])
    ->name('users.index')
    ->middleware('can:user_view');

// Combinar validaciones de rol y permiso
Route::resource('users', UserController::class)
    ->middleware(['role:admin', 'can:user_manage']);
```

### Controladores

#### Constructor con Middleware de Roles y Permisos
```php
class UserController extends Controller
{
    public function __construct()
    {
        // Restringir acceso a roles específicos
        $this->middleware('role:admin,user');
        
        // Restringir acciones específicas por permiso
        $this->middleware('can:user_create')->only(['create', 'store']);
        $this->middleware('can:user_update')->only(['edit', 'update']);
        $this->middleware('can:user_delete')->only('destroy');
    }
}
```

### Vistas (Blade)

#### Condicionales de Permisos
```blade
@can('user_view')
    <div>Información detallada de usuarios</div>
@endcan

@can('role_create')
    <button>Crear Nuevo Rol</button>
@endcan

@can('task_edit')
    <form>Editar Tarea</form>
@endcan

@cannot('task_edit')
    <p>No tienes permisos para editar tareas</p>
@endcannot

tambien  este genera mas consulta pero  puede tener  mas de un permiso requerido
    @permission('role_create')
        <button>Crear Nuevo Rol</button>
    @endpermission

    @permission('task_edit')
        <form>Editar Tarea</form>
    @endpermission
```

#### Condicionales de Roles
```blade
@role('admin')
    <section>Panel de Administración</section>
@endrole

@role('user')
    <section>Panel de Usuario</section>
@endrole

{{-- Múltiples roles --}}
@role(['admin', 'manager'])
    <div>Contenido para administradores y gerentes</div>
@endrole
```
### Rutas (web.php)

#### Validación por Rol Específico
```php
// Solo acceso para usuarios con rol 'admin'
Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])
    ->name('roles.permissions.edit')
    ->middleware('role:admin');

// Validación por Múltiples Roles
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware('role:admin,user');
```

#### Validación por Permiso
```php
// Requiere permiso específico
Route::get('/users', [UserController::class, 'index'])
    ->name('users.index')
    ->middleware('can:user_view');

// Combinar validaciones de rol y permiso
Route::resource('users', UserController::class)
    ->middleware(['role:admin', 'can:user_manage']);
```

### Controladores

#### Constructor con Middleware de Roles y Permisos
```php
class UserController extends Controller
{
    public function __construct()
    {
        // Restringir acceso a roles específicos
        $this->middleware('role:admin,user');
        
        // Restringir acciones específicas por permiso
        $this->middleware('can:user_create')->only(['create', 'store']);
        $this->middleware('can:user_update')->only(['edit', 'update']);
        $this->middleware('can:user_delete')->only('destroy');
    }
}
```

#### Debugging de Roles y Permisos
```blade
{{-- Mostrar información de roles y permisos del usuario --}}
@php
    $userRoleInfo = auth()->user()->debugRoleAndPermissions();
    dump($userRoleInfo);
@endphp
```

### Ejemplos Avanzados

#### Validación Programática
```php
// En un controlador o middleware
public function someMethod()
{
    $user = auth()->user();
    
    // Verificación de rol
    if ($user->hasRole('admin')) {
        // Lógica para admin
    }
    
    // Verificación de permiso
    if ($user->hasPermission('create_users')) {
        // Lógica para creación de usuarios
    }
    
    // Verificación de múltiples roles
    if ($user->hasAnyRole(['admin', 'manager'])) {
        // Lógica para roles administrativos
    }
}
```

### limpiar  cache para usario 
    util  para cuando  se eliminan o desasignar roles 
```php
 // Limpiar la caché de permisos
        Cache::increment(User::PERMISSIONS_VERSION_KEY);
```

## Middleware Personalizado

### RoleMiddleware
```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();
        
        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        abort(403, 'Acceso no autorizado');
    }
}
```

## Service Provider

### AuthServiceProvider
```php
<?php
namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPolicies();

        // Gate global para verificación de permisos
        Gate::before(function ($user, $ability) {
            return $user->hasPermission($ability) ?: null;
        });

        // Directiva Blade para roles
        Blade::directive('role', function ($role) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$role})): ?>";
        });

        Blade::directive('endrole', function () {
            return "<?php endif; ?>";
        });

        Blade::directive('permission', function ($permissions) {
            return "<?php 
                \$permissionsArray = is_array({$permissions}) ? {$permissions} : explode(',', {$permissions});
                if(auth()->check() && auth()->user()->hasAnyPermission(\$permissionsArray)): 
            ?>";
        });

         Blade::directive('endpermission', function () {
            return "<?php endif; ?>";
        });
    }
}
```

## Seeder Recomendado

### RolePermissionSeeder
```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Crear roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        // Crear permisos
        $permissions = [
            'user_view', 'user_create', 'user_update', 'user_delete',
            'role_view', 'role_create', 'role_update', 'role_delete'
        ];

        foreach ($permissions as $permissionName) {
            Permission::create(['name' => $permissionName]);
        }

        // Asignar todos los permisos al rol admin
        $adminRole->permissions()->sync(Permission::all());
    }
}
```

## Configuración Final

1. Ejecutar migraciones
```bash
php artisan migrate
```

2. Ejecutar seeders
```bash
php artisan db:seed --class=RolePermissionSeeder
```

## Estrategias de Caché

### Versionado de Permisos
- Cada cambio en roles/permisos incrementa versión
- Invalida cachés existentes
- Garantiza consistencia de datos

### Consultas Optimizadas
- Caché global de roles
- Consultas en memoria
- Reducción de consultas a base de datos

## Consideraciones Importantes
- Los middlewares `role:` y `can:` deben estar registrados en `app/Http/Kernel.php`
- Configurar correctamente los seeders de roles y permisos
- Mantener actualizada la caché de permisos

## Extensibilidad
- Fácil agregar nuevos roles
- Modificar permisos dinámicamente
- Integrable con sistemas de autenticación existentes

## Contribuciones
- Pull requests son bienvenidos
- Seguir estándares de código Laravel
- Documentar cambios