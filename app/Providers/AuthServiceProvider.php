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
     * Registra cualquier servicio de autenticación y autorización.
     */
    public function boot(): void
    {
        // Registra las políticas definidas en $policies
        $this->registerPolicies();

        // Define una verificación de permisos usando Gate y antes de cualquier otra comprobación
        Gate::before(function ($user, $ability) {
            // Si no hay un usuario autenticado, devuelve null
            if (!$user) return null;
            
            // Verifica si el usuario tiene el permiso solicitado
            return $user->hasPermission($ability) ?: null;
        });

        /**
         * Directivas Blade para verificación de roles
         */
        
        // Directiva @role para verificar si el usuario tiene un rol específico
        Blade::directive('role', function ($role) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$role})): ?>";
        });

        // Directiva @endrole para cerrar la condición de @role
        Blade::directive('role', function ($roles) {
            return "<?php 
                \$rolesArray = is_array({$roles}) ? {$roles} : explode(',', {$roles});
                if(auth()->check() && auth()->user()->hasAnyRole(\$rolesArray)): 
            ?>";
        });
        
        Blade::directive('endrole', function () {
            return "<?php endif; ?>";
        });
        
        
    }
}