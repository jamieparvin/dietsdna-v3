<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'content_hash',
    'meal_type',
    'snapshot',
])]
class SavedMeal extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }
}