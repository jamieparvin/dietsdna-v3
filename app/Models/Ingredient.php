<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'food_code',
    'name',
    'category',
    'sub_category',
    'preparation_state',
    'energy_kcal',
    'protein_g',
    'carbohydrate_g',
    'sugars_g',
    'fat_g',
    'saturates_g',
    'fibre_g',
    'salt_g',
    'is_active',
])]
class Ingredient extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'energy_kcal' => 'decimal:5',
            'protein_g' => 'decimal:5',
            'carbohydrate_g' => 'decimal:5',
            'sugars_g' => 'decimal:5',
            'fat_g' => 'decimal:5',
            'saturates_g' => 'decimal:5',
            'fibre_g' => 'decimal:5',
            'salt_g' => 'decimal:5',
            'is_active' => 'boolean',
        ];
    }
}