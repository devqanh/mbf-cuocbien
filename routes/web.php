<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Items CRUD (Luckysheet)
    Route::get   ('/items',           [ItemController::class, 'index'])  ->name('items.index');
    Route::get   ('/items/data',      [ItemController::class, 'data'])   ->name('items.data');
    Route::post  ('/items',           [ItemController::class, 'store'])  ->name('items.store');
    Route::post  ('/items/bulk',      [ItemController::class, 'bulk'])   ->name('items.bulk');
    Route::post  ('/items/reset-snapshot', [ItemController::class, 'resetSnapshot'])->name('items.resetSnapshot');
    Route::put   ('/items/{item}',    [ItemController::class, 'update']) ->name('items.update');
    Route::delete('/items/{item}',    [ItemController::class, 'destroy'])->name('items.destroy');

    // Users
    Route::get   ('/users',         [UserController::class, 'index'])  ->name('users.index');
    Route::post  ('/users',         [UserController::class, 'store'])  ->name('users.store');
    Route::put   ('/users/{user}',  [UserController::class, 'update']) ->name('users.update');
    Route::delete('/users/{user}',  [UserController::class, 'destroy'])->name('users.destroy');

    // Roles & Permissions
    Route::get   ('/roles',         [RoleController::class, 'index'])  ->name('roles.index');
    Route::post  ('/roles',         [RoleController::class, 'store'])  ->name('roles.store');
    Route::put   ('/roles/{role}',  [RoleController::class, 'update']) ->name('roles.update');
    Route::delete('/roles/{role}',  [RoleController::class, 'destroy'])->name('roles.destroy');
});
