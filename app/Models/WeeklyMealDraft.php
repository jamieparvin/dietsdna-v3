<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'start_date', 'status', 'source_signature', 'meal_count',
    'slots', 'generated', 'proposals', 'completed_types', 'error', 'confirmed_at',
    'mode', 'baseline_fingerprint',
])]
class WeeklyMealDraft extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'meal_count' => 'integer',
            'slots' => 'array',
            'generated' => 'array',
            'proposals' => 'array',
            'completed_types' => 'integer',
            'confirmed_at' => 'immutable_datetime',
        ];
    }
}
