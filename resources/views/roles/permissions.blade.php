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
                    <div class="card-body">
                        <h3 class="mb-0">Gestionar Permisos para el Rol: <strong>{{ $role->display_name }}</strong></h3>
                        <form action="{{ route('roles.permissions.update', $role->id) }}" method="POST">
                            @csrf
                            @method('PUT')
            
                            @foreach($permissions as $module => $modulePermissions)
                                <div class="mb-4">
                                    <h5 class="text-secondary border-bottom pb-2">{{ ucfirst($module) }}</h5>
                                    <div class="row">
                                        @foreach($modulePermissions as $permission)
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input 
                                                        type="checkbox" 
                                                        name="permissions[]" 
                                                        value="{{ $permission->id }}" 
                                                        class="form-check-input" 
                                                        id="permission-{{ $permission->id }}"
                                                        {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}
                                                    >
                                                    <label class="form-check-label" for="permission-{{ $permission->id }}">
                                                        {{ $permission->display_name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
            
                            <div class="text-end">
                                <x-primary-button class="mt-3" type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Guardar Permisos
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</x-app-layout>
