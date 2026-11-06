<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'goal', 'height_cm', 'weight_kg', 'activity_level', 'dietary_preference',
    'allergies', 'foods_to_avoid', 'date_of_birth', 'target_weight_kg',
    'meals_per_day', 'snacks_per_day', 'cooking_minutes',
    'calculation_sex', 'planning_context', 'planning_circumstances', 'resistance_training',
    'macro_mode', 'macro_goal', 'macro_protein_percent',
    'macro_carbohydrate_percent', 'macro_fat_percent',
])]
class NutritionProfile extends Model
{
    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:2', 'weight_kg' => 'decimal:9',
            'date_of_birth' => 'date', 'target_weight_kg' => 'decimal:9',
            'meals_per_day' => 'integer', 'snacks_per_day' => 'integer',
            'cooking_minutes' => 'integer', 'resistance_training' => 'boolean',
            'planning_circumstances' => 'array',
            'macro_protein_percent' => 'decimal:2',
            'macro_carbohydrate_percent' => 'decimal:2',
            'macro_fat_percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
