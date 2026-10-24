<?php

use App\Http\Controllers\MacroPreferenceController;
use Illuminate\Support\Facades\Route;

Route::put('/nutrition-profile/macros', [MacroPreferenceController::class, 'update'])
    ->middleware('auth')
    ->name('nutrition-profile.macros.update');
