<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'description',
    'instructions',
    'servings',
    'status',
])]
class Recipe extends Model
{
    public const NUTRIENTS = [
        'energy_kcal' => 'Energy (kcal)',
        'protein_g' => 'Protein (g)',
        'carbohydrate_g' => 'Total carbohydrate (g)',
        'sugars_g' => 'Sugars (g)',
        'fat_g' => 'Fat (g)',
        'saturates_g' => 'Saturates (g)',
        'fibre_g' => 'Fibre (g)',
        'salt_g' => 'Salt (g)',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
        ];
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->withPivot(['quantity_g', 'position'])
            ->orderByPivot('position');
    }

    public function nutritionTotals(): array
    {
        $totals = array_fill_keys(array_keys(self::NUTRIENTS), 0.0);

        foreach ($this->ingredients as $ingredient) {
            $factor = (float) $ingredient->pivot->quantity_g / 100;

            foreach ($totals as $field => $total) {
                // Missing data stays unknown, rather than becoming zero.
                if ($total === null || $ingredient->{$field} === null) {
                    $totals[$field] = null;

                    continue;
                }

                $totals[$field] += (float) $ingredient->{$field} * $factor;
            }
        }

        return $totals;
    }
}