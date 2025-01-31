<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
{
    use HasFactory;

    /**
     * Método boot que se ejecuta automáticamente cuando el modelo se inicializa.
     * Se usa para definir eventos que afectan a la caché cuando se guardan o eliminan permisos.
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se dispara después de guardar un permiso (crear o actualizar)
        static::saved(function () {
            // Incrementa la versión de permisos en caché para invalidar versiones antiguas
            Cache::increment(User::PERMISSIONS_VERSION_KEY);
        });

        // Evento que se dispara después de eliminar un permiso
        static::deleted(function () {
            // Incrementa la versión de permisos en caché para invalidar versiones antiguas
            Cache::increment(User::PERMISSIONS_VERSION_KEY);
        });
    }

    /**
     * Relación muchos a muchos con la tabla roles.
     * Un permiso puede pertenecer a varios roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles() 
    {
        return $this->belongsToMany(Role::class);
    }
}
