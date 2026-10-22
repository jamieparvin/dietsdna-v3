<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\NutritionProfileController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::view('/dashboard', 'dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');
    });

Route::middleware('auth')->group(function () {
    Route::get('/nutrition-profile', [NutritionProfileController::class, 'edit'])
        ->name('nutrition-profile.edit');

    Route::put('/nutrition-profile', [NutritionProfileController::class, 'update'])
        ->name('nutrition-profile.update');
});