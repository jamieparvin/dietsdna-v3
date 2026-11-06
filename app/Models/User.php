<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'food_preferences_reviewed_at' => 'datetime',
        ];
    }

    public function nutritionProfile(): HasOne
    {
        return $this->hasOne(NutritionProfile::class);
    }

    public function foodPreferences(): BelongsToMany
    {
        return $this->belongsToMany(
            Ingredient::class,
            'ingredient_user_preferences'
        )
            ->withPivot('preference')
            ->withTimestamps();
    }

    public function weightEntries(): HasMany
    {
        return $this->hasMany(WeightEntry::class);
    }
}
