<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\IngredientImportController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\RecipeGenerationController;
use App\Http\Controllers\Admin\NutritionCalculatorSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoodPreferenceController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\MealIdeaController;
use App\Http\Controllers\NutritionProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\SavedMealController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        
    
        Route::get('/', AdminDashboardController::class)
            ->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');

        Route::get('/users/{user}', [UserController::class, 'show'])
            ->whereNumber('user')
            ->name('users.show');

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

        Route::get('/recipes/generate', [
            RecipeGenerationController::class,
            'create',
        ])->name('recipes.generate.create');

        Route::post('/recipes/generate', [
            RecipeGenerationController::class,
            'store',
        ])->middleware('throttle:3,1')
            ->name('recipes.generate.store');

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

    Route::get('/food-preferences', [FoodPreferenceController::class, 'edit'])
        ->name('food-preferences.edit');

    Route::put('/food-preferences', [FoodPreferenceController::class, 'update'])
        ->name('food-preferences.update');

    Route::get('/meal-ideas', [MealIdeaController::class, 'index'])
        ->name('meal-ideas.index');

    Route::post('/meal-ideas', [MealIdeaController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('meal-ideas.store');
    
    Route::get('/saved-meals', [SavedMealController::class, 'index'])
        ->name('saved-meals.index');

    Route::post('/saved-meals', [SavedMealController::class, 'store'])
        ->name('saved-meals.store');

    Route::get('/meal-plan', [MealPlanController::class, 'index'])
        ->name('meal-plan.index');

    Route::get('/progress', [ProgressController::class, 'index'])
        ->name('progress.index');

    Route::post('/progress', [ProgressController::class, 'store'])
        ->name('progress.store');

    Route::put('/progress/{entry}', [ProgressController::class, 'update'])
        ->whereNumber('entry')
        ->name('progress.update');

    Route::delete('/progress/{entry}', [ProgressController::class, 'destroy'])
        ->whereNumber('entry')
        ->name('progress.destroy');

    Route::get('/shopping-list', [ShoppingListController::class, 'index'])
        ->name('shopping-list.index');

    Route::put('/shopping-list', [ShoppingListController::class, 'update'])
        ->name('shopping-list.update');

    Route::post('/meal-plan', [MealPlanController::class, 'store'])
        ->name('meal-plan.store');

    Route::delete('/meal-plan/{entry}', [MealPlanController::class, 'destroy'])
        ->whereNumber('entry')
        ->name('meal-plan.destroy');

    require __DIR__.'/meal-planning.php';
    require __DIR__.'/macro-preferences.php';
    require __DIR__.'/week-actions.php';
    require __DIR__.'/saved-meal-actions.php';
});