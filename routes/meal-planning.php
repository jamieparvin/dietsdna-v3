<?php

use App\Http\Controllers\MealPlanSwapController;
use App\Http\Controllers\WeeklyMealPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/weekly-meals', [WeeklyMealPlanController::class, 'create'])->name('weekly-meals.create');
    Route::post('/weekly-meals', [WeeklyMealPlanController::class, 'store'])->middleware('throttle:3,1')->name('weekly-meals.store');
    Route::get('/weekly-meals/{draft}', [WeeklyMealPlanController::class, 'show'])->whereNumber('draft')->name('weekly-meals.show');
    Route::get('/weekly-meals/{draft}/status', [WeeklyMealPlanController::class, 'status'])->whereNumber('draft')->name('weekly-meals.status');
    Route::post('/weekly-meals/{draft}/confirm', [WeeklyMealPlanController::class, 'confirm'])->whereNumber('draft')->name('weekly-meals.confirm');
    Route::post('/weekly-meals/{draft}/cancel', [WeeklyMealPlanController::class, 'cancel'])->whereNumber('draft')->name('weekly-meals.cancel');
    Route::get('/meal-plan/{entry}/swap', [MealPlanSwapController::class, 'create'])->whereNumber('entry')->name('meal-plan.swap');
    Route::post('/meal-plan/{entry}/swap/ideas', [MealPlanSwapController::class, 'generate'])->whereNumber('entry')->middleware('throttle:3,1')->name('meal-plan.swap.generate');
    Route::put('/meal-plan/{entry}/swap', [MealPlanSwapController::class, 'update'])->whereNumber('entry')->name('meal-plan.swap.update');
});
