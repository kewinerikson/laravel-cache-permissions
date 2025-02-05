<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2>Bienvenido al Dashboard</h2>

                    @can('user_view')
                        <p>Tienes permiso para ver usuarios.</p>
                    @endcan
                    
                    @can('role_create')
                        <p>Tienes permiso para crear roles.</p>
                    @endcan
                    @permission('task_edit,user_view')
                        <p>Tienes permiso para editar tareas y/o ver usuarios.</p>
                    @endpermission
                    {{-- @dd(auth()->user()->roles) --}}
                    
                    {{-- @cannot('tasks_edit')
                        <p>No tienes permiso para editar tareas.</p>
                    @endcannot --}}

                    @role('admin')
                        <p>Este contenido es solo para administradores.</p>
                    @endrole
                    
                    @role('user')
                        <p>Este contenido es solo para usuarios.</p>
                    @endrole
                    @role(['admin', 'user'])
                        <p>Contenido para administradores o usuarios</p>
                    @endrole
                    {{-- @dd(auth()->user()->debugRoleAndPermissions()); --}}

                    @role('admin')
                    <form action="{{ route('roles.updateRole', auth()->user()) }}" method="POST">
                        @csrf
                        <label for="role">Selecciona un nuevo rol:</label>
                        <br>
                        {{-- <select name="role_id" id="role" class="form-control" required> --}}
                            <x-select name="role_id" id="role" required>
                            @foreach(\App\Models\Role::all() as $role)
                                <option value="{{ $role->id }}" {{ auth()->user()->roles->contains($role->id) ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </x-select>
                        <br>
                        <x-primary-button class="mt-3" type="submit">Actualizar Rol</x-primary-button>
                    </form>
                    <br>
                    <br>
                    <form action="{{ route('cache.clear.permissions') }}" method="POST">
                        @csrf
                        <x-primary-button type="submit" class="btn btn-danger">
                            <i class="fas fa-sync-alt"></i> Limpiar Caché de Permisos y Roles
                        </x-primary-button>
                    </form>
                    @endrole
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
