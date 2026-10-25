<?php

use App\Http\Controllers\MealPlanWeekController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/meal-plan/week/clear', [MealPlanWeekController::class, 'create'])
        ->name('meal-plan.clear.create');
    Route::post('/meal-plan/week/clear', [MealPlanWeekController::class, 'store'])
        ->name('meal-plan.clear.store');
});
