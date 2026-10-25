<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'week_start',
    'item_key',
    'quantity_g',
])]
class ShoppingListCheck extends Model
{
    public function setWeekStartAttribute($value): void
    {
        // Store a DATE consistently on SQLite as well as MySQL. Eloquent's
        // ordinary date cast otherwise writes a midnight timestamp on SQLite.
        $this->attributes['week_start'] = CarbonImmutable::parse($value, 'Europe/London')->toDateString();
    }

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'quantity_g' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
