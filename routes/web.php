<?php

use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\IngredientImportController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\NutritionCalculatorSettingsController;
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
        Route::view('/', 'admin.dashboard')
            ->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');

        Route::get('/ingredients/import', [
            IngredientImportController::class,
            'create',
        ])->name('ingredients.import.create');

        Route::post('/ingredients/import/preview', [
            IngredientImportController::class,
            'preview',
        ])->name('ingredients.import.preview');

        Route::post('/ingredients/import/confirm', [
            IngredientImportController::class,
            'confirm',
        ])->name('ingredients.import.confirm');

        Route::resource('ingredients', IngredientController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::resource('recipes', RecipeController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

        Route::get('/calculator-settings', [
            NutritionCalculatorSettingsController::class,
            'edit',
        ])->name('calculator-settings.edit');

        Route::put('/calculator-settings', [
            NutritionCalculatorSettingsController::class,
            'update',
        ])->name('calculator-settings.update');
    });    

Route::middleware('auth')->group(function () {
    Route::get('/nutrition-profile', [NutritionProfileController::class, 'edit'])
        ->name('nutrition-profile.edit');

    Route::put('/nutrition-profile', [NutritionProfileController::class, 'update'])
        ->name('nutrition-profile.update');
});