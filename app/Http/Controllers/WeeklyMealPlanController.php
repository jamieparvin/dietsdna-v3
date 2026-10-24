<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\MealPlanEntry;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\WeeklyMealPlanner;
use App\Services\Meals\WeeklyMealSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyMealPlanController extends Controller
{
    public function create(Request $request, WeeklyMealPlanner $planner)
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($data['date'] ?? now('Europe/London')->toDateString(), 'Europe/London')->startOfWeek(1);
        $blocked = null;
        $source = null;

        try {
            $source = $planner->source($request->user());
        } catch (ValidationException $exception) {
            $blocked = collect($exception->errors())->flatten()->first();
        }

        $recent = WeeklyMealDraft::query()->where('user_id', $request->user()->id)
            ->latest('id')->limit(5)->get();

        return view('weekly-meals.create', compact('start', 'source', 'blocked', 'recent'));
    }

    public function store(Request $request, WeeklyMealPlanner $planner)
    {
        $data = $request->validate(['start_date' => ['required', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($data['start_date'], 'Europe/London')->startOfWeek(1);

        $draft = DB::transaction(function () use ($request, $planner, $start) {
            $user = $request->user();
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            $active = WeeklyMealDraft::query()->where('user_id', $user->id)
                ->whereIn('status', ['queued', 'generating'])->latest('id')->first();

            if ($active) {
                return $active;
            }

            $source = $planner->source($user);
            $filled = MealPlanEntry::query()->where('user_id', $user->id)
                ->whereBetween('plan_date', [$start->toDateString(), $start->addDays(6)->toDateString()])
                ->whereIn('slot', array_keys($source['slots']))->count();

            if ($filled >= 7 * count($source['slots'])) {
                throw ValidationException::withMessages(['week' => 'This week is already filled. Use Swap meal to change individual meals.']);
            }

            $draft = WeeklyMealDraft::query()->create([
                'user_id' => $user->id,
                'start_date' => $start->toDateString(),
                'status' => 'queued',
                'source_signature' => $source['signature'],
                'meal_count' => $source['context']['meal_count'],
                'slots' => $source['slots'],
            ]);

            GenerateWeeklyMealDraft::dispatch($draft->id)->afterCommit();

            return $draft;
        });

        return redirect()->route('weekly-meals.show', $draft->id);
    }

    public function show(Request $request, int $draft)
    {
        $draft = $this->owned($request, $draft);
        $existing = MealPlanEntry::query()->where('user_id', $request->user()->id)
            ->whereBetween('plan_date', [$draft->start_date->toDateString(), $draft->start_date->addDays(6)->toDateString()])
            ->get()->keyBy(fn ($entry) => $entry->plan_date->format('Y-m-d').'|'.$entry->slot);
        $proposals = collect($draft->proposals ?? [])->keyBy(fn ($row) => $row['date'].'|'.$row['slot']);
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $draft->start_date->addDays($offset);
            $rows = [];
            $totals = ['energy_kcal' => 0, 'protein_g' => 0, 'carbohydrate_g' => 0, 'fat_g' => 0];

            $orderedSlots = collect($draft->slots)->sortBy(
                fn ($label, $slot) =>
                    \App\Services\Meals\MealPlanSlots::displayOrder($slot)
            );

            foreach ($orderedSlots as $slot => $label) {
                $key = $date->toDateString().'|'.$slot;
                $entry = $existing->get($key);
                $proposal = $proposals->get($key);
                $snapshot = $entry?->snapshot ?? ($proposal['snapshot'] ?? null);
                $rows[] = ['label' => $label, 'snapshot' => $snapshot, 'existing' => (bool) $entry];

                if ($snapshot) {
                    foreach (array_keys($totals) as $field) {
                        $totals[$field] += $snapshot['totals'][$field];
                    }
                }
            }

            // Older slots outside today's profile remain preserved and counted.
            foreach ($existing as $entry) {
                if ($entry->plan_date->format('Y-m-d') === $date->toDateString() && ! isset($draft->slots[$entry->slot])) {
                    $rows[] = ['label' => ucfirst(str_replace('_', ' ', $entry->slot)).' — previous pattern', 'snapshot' => $entry->snapshot, 'existing' => true];
                    foreach (array_keys($totals) as $field) {
                        $totals[$field] += $entry->snapshot['totals'][$field];
                    }
                }
            }

            $days[] = compact('date', 'rows', 'totals');
        }

        $typeCount = count(array_unique(array_map(fn ($slot) => WeeklyMealSchedule::typeForSlot($slot), array_keys($draft->slots))));

        return view('weekly-meals.show', compact('draft', 'days', 'typeCount'));
    }

    public function status(Request $request, int $draft)
    {
        $draft = $this->owned($request, $draft);

        return response()->json([
            'status' => $draft->status,
            'completed_types' => $draft->completed_types,
        ])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, int $draft, WeeklyMealPlanner $planner)
    {
        $owned = $this->owned($request, $draft);
        $result = $planner->confirm($owned->id, $request->user());
        $message = $result['already_confirmed']
            ? 'This preview has already been added. No duplicate meals were created.'
            : $result['added'].' meals added. Existing meals were preserved.';

        return redirect()->route('meal-plan.index', ['date' => $owned->start_date->toDateString()])->with('status', $message);
    }

    public function cancel(Request $request, int $draft)
    {
        $owned = $this->owned($request, $draft);

        WeeklyMealDraft::query()->whereKey($owned->id)->whereIn('status', ['queued', 'generating', 'ready'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        return redirect()->route('weekly-meals.show', $owned->id);
    }

    private function owned(Request $request, int $id): WeeklyMealDraft
    {
        return WeeklyMealDraft::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
