<?php

namespace App\Http\Controllers;

use App\Models\ShoppingListCheck;
use App\Services\Shopping\ShoppingListBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShoppingListController extends Controller
{
    public function index(
        Request $request,
        ShoppingListBuilder $builder
    ) {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $weekStart = $this->weekStart($data['date'] ?? null);

        $list = $builder->build($request->user(), $weekStart);

        $checks = ShoppingListCheck::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('week_start', $weekStart->toDateString())
            ->get()
            ->keyBy('item_key');

        $items = collect($list['items'])->map(function (array $item) use ($checks) {
            $check = $checks->get($item['item_key']);

            $item['is_checked'] = $check !== null
                && (int) round((float) $check->quantity_g * 100)
                    >= (int) round((float) $item['quantity_g'] * 100);

            return $item;
        });

        return view('shopping-list.index', [
            'weekStart' => $list['week_start'],
            'weekEnd' => $list['week_end'],
            'mealCount' => $list['meal_count'],
            'groups' => $items->where('is_checked', false)->groupBy('category'),
            'purchasedItems' => $items->where('is_checked', true)->values(),
            'needsReview' => $list['needs_review'],
            'incomplete' => $list['incomplete'],
            'itemCount' => $items->count(),
            'checkedCount' => $items->where('is_checked', true)->count(),
            'purchasedOpen' => $request->boolean('purchased'),
            'weekOptions' => collect(range(-2, 4))->map(fn ($offset) => $weekStart->addWeeks($offset)),
        ]);
    }

    public function update(
        Request $request,
        ShoppingListBuilder $builder
    ) {
        $data = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
            'item_key' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'quantity_g' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999999.99',
                'decimal:0,2',
            ],
            'checked' => ['required', 'boolean'],
        ]);

        $weekStart = $this->weekStart($data['week_start']);

        $result = DB::transaction(function () use (
            $request,
            $builder,
            $data,
            $weekStart
        ) {
            $user = $request->user();

            // Use the same user lock as meal-plan changes.
            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            $list = $builder->build($user, $weekStart);

            $item = collect($list['items'])
                ->firstWhere('item_key', $data['item_key']);

            if (! $item) {
                throw ValidationException::withMessages([
                    'shopping_list' => 'This item is no longer in your plan. Refresh the list.',
                ]);
            }

            $submittedQuantity = (int) round(
                (float) $data['quantity_g'] * 100
            );

            $currentQuantity = (int) round(
                (float) $item['quantity_g'] * 100
            );

            if ($submittedQuantity !== $currentQuantity) {
                throw ValidationException::withMessages([
                    'shopping_list' => 'The quantity changed after this page opened. '
                        .'Refresh the list before updating it.',
                ]);
            }

            $identity = [
                'user_id' => $user->id,
                'week_start' => $weekStart->toDateString(),
                'item_key' => $item['item_key'],
            ];

            $matching = ShoppingListCheck::query()->where('user_id', $user->id)
                ->whereDate('week_start', $weekStart->toDateString())->where('item_key', $item['item_key']);
            $existing = (clone $matching)->lockForUpdate()->first();
            if ((bool) $data['checked']) {
                if ($existing) {
                    $existing->update(['quantity_g' => $item['quantity_g']]);
                } else {
                    ShoppingListCheck::create($identity + ['quantity_g' => $item['quantity_g']]);
                }
            } else {
                $matching->delete();
            }
            $checks = ShoppingListCheck::query()->where('user_id', $user->id)
                ->whereDate('week_start', $weekStart->toDateString())->pluck('quantity_g', 'item_key');
            $checkedCount = collect($list['items'])->filter(fn ($row) => isset($checks[$row['item_key']])
                && (int) round((float) $checks[$row['item_key']] * 100) >= (int) round((float) $row['quantity_g'] * 100))->count();

            return ['item_key' => $item['item_key'], 'is_checked' => (bool) $data['checked'],
                'quantity_g' => $item['quantity_g'], 'checked_count' => $checkedCount,
                'item_count' => count($list['items'])];
        });

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->to(route('shopping-list.index', [
            'date' => $weekStart->toDateString(), 'purchased' => (bool) $data['checked'] ? 1 : 0,
        ]).'#shopping-item-'.$data['item_key']);
    }

    private function weekStart(?string $date): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $date ?? now('Europe/London')->toDateString(),
            'Europe/London'
        )->startOfWeek(1)->startOfDay();
    }
}
