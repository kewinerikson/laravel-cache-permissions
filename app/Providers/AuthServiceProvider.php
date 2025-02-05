<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Blade;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de modelos a políticas de autorización.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Definir aquí los modelos y sus respectivas políticas
    ];

    /**
     * Registra servicios de autenticación y autorización.
     * Configura directivas Blade personalizadas para roles y permisos.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Gate global para verificación de permisos.
         * Se ejecuta antes de cualquier otra comprobación de autorización.
         * 
         * @param User|null $user Usuario actual
         * @param string $ability Permiso a verificar
         * @return bool|null
         */
        Gate::before(function ($user, $ability) {
            if (!$user) return null;
            return $user->hasPermission($ability) ?: null;
        });

        /**
         * Directiva para verificar un rol específico.
         * Uso: @role('admin')
         * 
         * @param string|array $role Rol o roles a verificar
         */
        Blade::directive('role', function ($role) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$role})): ?>";
        });

        /**
         * Directiva para verificar múltiples roles.
         * Uso: @role(['admin', 'editor'])
         * 
         * @param string|array $roles Array de roles o string separada por comas
         */
        Blade::directive('role', function ($roles) {
            return "<?php 
                \$rolesArray = is_array({$roles}) ? {$roles} : explode(',', {$roles});
                if(auth()->check() && auth()->user()->hasAnyRole(\$rolesArray)): 
            ?>";
        });
        
        /**
         * Cierra el bloque @role
         */
        Blade::directive('endrole', function () {
            return "<?php endif; ?>";
        });

        /**
         * Directiva para verificar permisos.
         * Uso: @permission(['edit-posts', 'create-posts'])
         * 
         * @param string|array $permissions Array de permisos o string separada por comas
         */
        Blade::directive('permission', function ($permissions) {
            return "<?php 
                \$permissionsArray = is_array({$permissions}) ? {$permissions} : explode(',', {$permissions});
                if(auth()->check() && auth()->user()->hasAnyPermission(\$permissionsArray)): 
            ?>";
        });

        /**
         * Cierra el bloque @permission
         */
        Blade::directive('endpermission', function () {
            return "<?php endif; ?>";
        });
    }
}