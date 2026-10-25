<?php

namespace App\Services\Shopping;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Services\Nutrition\IngredientPreparation;
use Carbon\CarbonImmutable;

class ShoppingListBuilder
{
    public function build(User $user, CarbonImmutable $weekStart): array
    {
        $start = $weekStart->setTimezone('Europe/London')->startOfWeek(1)->startOfDay();
        // Draft proposals and unplanned saved meals never enter the shopping list.
        $entries = MealPlanEntry::query()->where('user_id', $user->id)
            ->where('plan_date', '>=', $start->toDateString())
            ->where('plan_date', '<', $start->addWeek()->toDateString())
            ->orderBy('plan_date')->orderBy('id')->get();
        $ids = $entries->flatMap(function ($entry) {
            $rows = $entry->snapshot['ingredients'] ?? null;

            return is_array($rows) ? collect($rows)->filter(fn ($row) => is_array($row))->pluck('ingredient_id') : [];
        })->filter(fn ($id) => $this->validId($id))->unique()->values();
        $catalog = Ingredient::query()->with('shoppingIngredient')->whereIn('id', $ids)->get()->keyBy('id');
        $items = $review = [];

        foreach ($entries as $entry) {
            $rows = $entry->snapshot['ingredients'] ?? null;
            if (! is_array($rows) || $rows === []) {
                $review[] = $this->review($entry, [], 'This meal has no usable ingredient list. Review its saved ingredients.');

                continue;
            }
            foreach ($rows as $row) {
                $row = is_array($row) ? $row : [];
                $id = $row['ingredient_id'] ?? null;
                $name = $this->text($row['name'] ?? null);
                $preparation = $this->text($row['preparation_state'] ?? null);
                $quantity = $row['quantity_g'] ?? null;
                if (! $this->validId($id) || ! $name || ! $preparation || ! $this->validQuantity($quantity)) {
                    $review[] = $this->review($entry, $row, 'Missing or invalid ingredient identity, preparation or planned grams.');

                    continue;
                }

                $source = $catalog->get((int) $id);
                $mapped = $source && ($source->shopping_ingredient_id !== null || $source->shopping_multiplier !== null);
                $target = null;
                $multiplier = 1.0;
                if ($mapped) {
                    $target = $source->shoppingIngredient;
                    $multiplier = $source->shopping_multiplier;
                    if (! $target || ! is_numeric($multiplier) || ! is_finite((float) $multiplier)
                        || $multiplier <= 0 || $multiplier > 100
                        || IngredientPreparation::normalise($source->preparation_state) !== IngredientPreparation::normalise($preparation)
                        || ! $this->text($target->preparation_state)
                        || ! $this->text($target->name)
                        || ($source->id === $target->id && (float) $multiplier !== 1.0)
                        || ($target->shopping_ingredient_id !== null && (int) $target->shopping_ingredient_id !== (int) $target->id)) {
                        $review[] = $this->review($entry, $row, 'The explicit purchase mapping is missing, invalid or for a different preparation.');

                        continue;
                    }
                } elseif (! IngredientPreparation::purchaseReady($preparation)) {
                    $review[] = $this->review($entry, $row, 'This preparation needs an explicit purchase mapping. Cooked grams have not been converted.');

                    continue;
                }

                // Direct purchases retain snapshot preparation even when the
                // ingredient record was renamed, deactivated or deleted.
                $purchaseId = $target ? (int) $target->id : (int) $id;
                $purchasePreparation = $target ? trim($target->preparation_state) : $preparation;
                $key = hash('sha256', json_encode([
                    'version' => 'shopping-purchase-v1', 'ingredient_id' => $purchaseId,
                    'preparation_state' => IngredientPreparation::normalise($purchasePreparation),
                ], JSON_THROW_ON_ERROR));
                $combined = ($items[$key]['purchase_grams'] ?? 0) + (float) $quantity * (float) $multiplier;
                if (! is_finite($combined) || $combined > 999999999999.99) {
                    $review[] = $this->review($entry, $row, 'The purchase quantity is outside the supported range.');

                    continue;
                }
                if (! isset($items[$key])) {
                    $items[$key] = [
                        'item_key' => $key, 'ingredient_id' => $purchaseId,
                        'name' => $target ? trim($target->name) : $name,
                        'preparation_state' => $purchasePreparation,
                        'category' => $this->text(($target ?? $source)?->category) ?? 'Other ingredients',
                        'purchase_grams' => 0.0, 'contributions' => [],
                    ];
                }
                $items[$key]['purchase_grams'] = $combined;
                $items[$key]['contributions'][] = [$entry, $row];
            }
        }
        foreach ($items as $key => &$item) {
            // Combine before rounding; checks store hundredths of grams.
            $item['quantity_g'] = number_format(round($item['purchase_grams'], 2), 2, '.', '');
            if ((float) $item['quantity_g'] <= 0) {
                foreach ($item['contributions'] as [$entry, $row]) {
                    $review[] = $this->review($entry, $row, 'The purchase quantity is smaller than the supported 0.01 g precision.');
                }
                unset($items[$key]);
            }
            unset($item['purchase_grams'], $item['contributions']);
        }
        unset($item);
        $items = collect($items)->sort(fn ($a, $b) => strnatcasecmp($a['category'], $b['category'])
            ?: strnatcasecmp($a['name'], $b['name'])
            ?: strnatcasecmp($a['preparation_state'], $b['preparation_state'])
            ?: strcmp($a['item_key'], $b['item_key'])
        )->values()->all();

        return ['week_start' => $start, 'week_end' => $start->addDays(6),
            'meal_count' => $entries->count(), 'items' => $items,
            'needs_review' => $review, 'incomplete' => $review !== []];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function validId(mixed $id): bool
    {
        return (is_int($id) || is_string($id)) && ctype_digit((string) $id) && (int) $id > 0;
    }

    private function validQuantity(mixed $quantity): bool
    {
        return is_numeric($quantity) && is_finite((float) $quantity) && $quantity > 0 && $quantity <= 100000;
    }

    private function review(MealPlanEntry $entry, array $row, string $reason): array
    {
        $quantity = $row['quantity_g'] ?? null;

        return ['name' => $this->text($row['name'] ?? null) ?? 'Unknown ingredient',
            'preparation_state' => $this->text($row['preparation_state'] ?? null) ?? 'Unknown preparation',
            'quantity_g' => $quantity,
            'quantity_label' => $quantity === null ? 'Unknown' : (is_scalar($quantity) && ! is_bool($quantity)
                ? (string) $quantity : json_encode($quantity, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)),
            'reason' => $reason, 'entry_id' => $entry->id,
            'meal_name' => $this->text($entry->snapshot['name'] ?? null) ?? 'Planned meal',
            'plan_date' => $entry->plan_date->format('Y-m-d')];
    }
}
