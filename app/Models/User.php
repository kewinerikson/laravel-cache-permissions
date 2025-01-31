<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Campos permitidos para asignación masiva
    protected $fillable = ['name', 'email', 'password'];
    
    // Campos ocultos en respuestas JSON
    protected $hidden = ['password', 'remember_token'];
    
    // Conversión de atributos a tipos específicos
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Constantes para claves de caché y tiempo de vida
    public const PERMISSIONS_VERSION_KEY = 'permissions_version'; // Versión de permisos
    public const GLOBAL_ROLES_KEY = 'global_roles_permissions'; // Clave global de roles
    private const CACHE_TTL = 1440; // Tiempo de caché en minutos (24 horas)
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
     * Registra un cambio en los permisos de un usuario en el log.
     */
    private static function logUserChange($user, $action)
    {
        Log::info("User permission change", [
            'user_id' => $user->id,
            'action' => $action,
            'timestamp' => now(),
        ]);
    }

    // ==============================
    // RELACIONES
    // ==============================

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

    // ==============================
    // GESTIÓN DE ROLES
    // ==============================

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

    // ==============================
    // SISTEMA DE CACHÉ GLOBAL
    // ==============================

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

    // ==============================
    // VERIFICACIÓN DE ROLES Y PERMISOS
    // ==============================

    /**
     * Verifica si el usuario tiene un rol específico o alguno de varios.
     */
    public function hasRole($roles) 
    {
        if (is_string($roles)) {
            $roles = explode(',', $roles);
        }
    
        $roles = array_map('trim', $roles);
    
        $userData = $this->getUserPermissionsAndRole();
    
        return in_array(strtolower($userData['role']), array_map('strtolower', $roles));
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

    // ==============================
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

    // ==============================
    // MÉTODOS DE DEBUG Y MONITOREO
    // ==============================

    /**
     * Obtiene información de los roles y permisos del usuario, útil para debugging.
     */
    public function debugRoleAndPermissions()
    {
        $userData = $this->getUserPermissionsAndRole();
        $globalRoles = self::getGlobalRoles();
        
        return [
            'user_id' => $this->id,
            'name' => $this->name,
            'role' => $userData['role'],
            'permissions' => $userData['permissions'],
            'cached_at' => date('Y-m-d H:i:s', $userData['cached_at']),
            'version' => $userData['version'],
            'global_roles_cached' => !empty($globalRoles),
            'memory_usage' => memory_get_usage(true)
        ];
    }

    /**
     * Obtiene métricas de caché relacionadas con los permisos y roles.
     */
    public static function getCacheMetrics()
    {
        return [
            'permissions_version' => Cache::get(self::PERMISSIONS_VERSION_KEY),
            'global_roles_cached' => Cache::has(self::GLOBAL_ROLES_KEY),
            'cached_roles' => array_keys(self::getGlobalRoles()),
            'last_updated' => now(),
            'cache_ttl' => self::CACHE_TTL,
        ];
    }

    /**
     * Refresca la caché de roles globales.
     */
    public static function refreshGlobalCache()
    {
        Cache::forget(self::GLOBAL_ROLES_KEY);
        self::incrementPermissionsVersion();
        return self::getGlobalRoles();
    }

    /**
     * Limpia completamente la caché de roles y permisos.
     */
    public static function clearAllCache()
    {
        Cache::forget(self::GLOBAL_ROLES_KEY);
        Cache::increment(self::PERMISSIONS_VERSION_KEY);
        Log::info('All permission caches cleared');
    }
}
