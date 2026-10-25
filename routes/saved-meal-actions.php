<?php

use App\Http\Controllers\SavedMealController;
use Illuminate\Support\Facades\Route;

Route::delete('/saved-meals/{savedMeal}', [SavedMealController::class, 'destroy'])
    ->whereNumber('savedMeal')->middleware('auth')->name('saved-meals.destroy');
