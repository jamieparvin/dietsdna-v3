<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
    'shopping_ingredient_id',
    'shopping_multiplier',
])]
class Ingredient extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Ingredient $ingredient) {
            // New foods are separate unless explicitly linked by an administrator.
            $ingredient->preference_key = hash('sha256', (string) Str::uuid());
            $ingredient->preference_group_key = $ingredient->preference_key;
            $ingredient->preference_food = $ingredient->name;
        });
    }

    public function snapshotFoodKey(): string
    {
        if ($this->preference_key) {
            return $this->preference_key;
        }

        if ($this->exists) {
            throw new \RuntimeException(
                'Ingredient preference identities are missing. '
                .'Run the pending migrations.'
            );
        }

        // Unsaved ingredient fixtures have no persisted identity.
        $normalise = fn ($value) => mb_strtolower(
            preg_replace('/\s+/u', ' ', trim((string) $value))
        );

        return hash('sha256', json_encode([
            $normalise($this->category),
            $normalise(trim((string) $this->preference_food) ?: $this->name),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function preferenceFoodKey(): string
    {
        return $this->preference_group_key ?: $this->snapshotFoodKey();
    }

    public function shoppingIngredient(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'shopping_ingredient_id'
        );
    }

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
            'shopping_ingredient_id' => 'integer',
            'shopping_multiplier' => 'decimal:6',
        ];
    }
}