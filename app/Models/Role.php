<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Define los campos que pueden ser asignados masivamente
    protected $fillable = ['name'];

    /**
     * Boot model: Se ejecuta automáticamente cuando el modelo se inicializa.
     * Se usa para agregar eventos a nivel de modelo.
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se dispara después de guardar un rol (crear o actualizar)
        static::saved(function () {
            // Invalida la caché global de roles en la clase User
            User::invalidateGlobalRolesCache();
        });

        // Evento que se dispara después de eliminar un rol
        static::deleted(function () {
            // Invalida la caché global de roles en la clase User
            User::invalidateGlobalRolesCache();
        });
    }

    /**
     * Relación muchos a muchos con la tabla permissions.
     * Un rol puede tener varios permisos.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Relación muchos a muchos con la tabla users.
     * Un rol puede estar asignado a varios usuarios.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Sincroniza los permisos del rol con los proporcionados en el array.
     * 
     * @param array $permissions Lista de IDs de permisos a asignar al rol.
     */
    public function syncPermissions(array $permissions)
    {
        // Actualiza los permisos asignados al rol en la tabla intermedia
        $this->permissions()->sync($permissions);

        // Invalida la caché global de roles en la clase User para reflejar los cambios
        User::invalidateGlobalRolesCache();
    }
}
