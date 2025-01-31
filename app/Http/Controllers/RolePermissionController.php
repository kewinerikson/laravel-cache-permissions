<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{

    public function __construct()
    {
        // Restringir acceso solo a usuarios con los roles 'admin' o 'user'
        // $this->middleware('role:admin,users');
        // //validacion por permisos  a funcion espesifica
        // $this->middleware('can:user_create')->only(['updateRole', 'update']);
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all()->groupBy('module'); // Agrupar permisos por módulo
        return view('roles.permissions', compact('role', 'permissions'));
    }



    public function update(Request $request, Role $role)
    {
        try {
            DB::transaction(function () use ($request, $role) {
                $role->permissions()->sync($request->input('permissions', []));
                
                Cache::forget(User::GLOBAL_ROLES_KEY);
                Cache::increment(User::PERMISSIONS_VERSION_KEY);
            });
    
            return redirect()->back()->with('success', 'Permisos actualizados correctamente.');
        } catch (\Exception $e) {
            Log::error("Error updating role permissions: " . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al actualizar los permisos.')
                ->withInput();
        }
    }


    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user->changeRole($request->role_id);

        return redirect()->back()->with('success', 'Rol actualizado correctamente.');
    }


    public function clearPermissionsCache()
    {
        Cache::forget(\App\Models\User::GLOBAL_ROLES_KEY);
        Cache::increment(\App\Models\User::PERMISSIONS_VERSION_KEY);
        // Log::info('All permission caches cleared');

        return back()->with('success', 'Caché de roles y permisos limpiada.');
    }
}

