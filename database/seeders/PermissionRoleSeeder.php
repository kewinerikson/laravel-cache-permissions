<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionRoleSeeder extends Seeder
{
    public function run()
    {
        // Limpiar tablas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('permission_role')->truncate();
        DB::table('role_user')->truncate();
        Permission::truncate();
        Role::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Crear rol admin
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
            'description' => 'Acceso total al sistema'
        ]);

        // Crear rol usuario
        $userRole = Role::create([
            'name' => 'user',
            'display_name' => 'Usuario',
            'description' => 'Usuario estándar'
        ]);

        // Definir permisos
        $permissions = [
            // Usuarios
            ['name' => 'user_view', 'module' => 'users', 'display_name' => 'Ver Usuarios'],
            ['name' => 'user_create', 'module' => 'users', 'display_name' => 'Crear Usuarios'],
            ['name' => 'user_edit', 'module' => 'users', 'display_name' => 'Editar Usuarios'],
            ['name' => 'user_delete', 'module' => 'users', 'display_name' => 'Eliminar Usuarios'],
            
            // Roles
            ['name' => 'role_view', 'module' => 'roles', 'display_name' => 'Ver Roles'],
            ['name' => 'role_create', 'module' => 'roles', 'display_name' => 'Crear Roles'],
            ['name' => 'role_edit', 'module' => 'roles', 'display_name' => 'Editar Roles'],
            ['name' => 'role_delete', 'module' => 'roles', 'display_name' => 'Eliminar Roles'],

            // Permisos
            ['name' => 'permission_view', 'module' => 'permissions', 'display_name' => 'Ver Permisos'],
            ['name' => 'permission_create', 'module' => 'permissions', 'display_name' => 'Crear Permisos'],
            ['name' => 'permission_edit', 'module' => 'permissions', 'display_name' => 'Editar Permisos'],
            ['name' => 'permission_delete', 'module' => 'permissions', 'display_name' => 'Eliminar Permisos']
        ];

        // Crear permisos y asignarlos al admin
        foreach ($permissions as $permission) {
            $perm = Permission::create($permission);
            DB::table('permission_role')->insert([
                'permission_id' => $perm->id,
                'role_id' => $adminRole->id
            ]);
        }

        // Crear usuarios
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('123456789')
        ]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('123456789')
        ]);

        // Asignar roles a usuarios
        DB::table('role_user')->insert([
            ['role_id' => $adminRole->id, 'user_id' => $admin->id],
            ['role_id' => $userRole->id, 'user_id' => $user->id]
        ]);
    }
}
