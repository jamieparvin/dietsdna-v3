<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'saved_meal_id',
    'plan_date',
    'slot',
    'snapshot',
])]
class MealPlanEntry extends Model
{
    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'snapshot' => 'array',
        ];
    }
}