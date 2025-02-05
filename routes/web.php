<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


    use App\Http\Controllers\RolePermissionController;
//validacion por  un rol especifico
Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])->name('roles.permissions.edit')->middleware('role:admin');
//validacion por  permiso 
// Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])->name('roles.permissions.edit') ->middleware('can:user_view');
//validacion por roles 
// Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])->name('roles.permissions.edit')->middleware('role:admin,user');
Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->name('roles.permissions.update');
Route::post('/roles/{user}/role', [RolePermissionController::class, 'updateRole'])->name('roles.updateRole');

Route::post('/clear-permissions-cache', [RolePermissionController::class, 'clearPermissionsCache'])
    ->name('cache.clear.permissions')
    ; // Solo accesible para administradores


Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
