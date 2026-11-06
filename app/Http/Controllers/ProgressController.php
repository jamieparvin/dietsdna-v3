<?php

namespace App\Http\Controllers;

use App\Models\WeightEntry;
use App\Services\Nutrition\BodyMassIndex;
use App\Services\Nutrition\WeightInput;
use App\Services\Nutrition\WeightUnits;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', Rule::in(['30', '90', '365'])],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $today = CarbonImmutable::now('Europe/London')->startOfDay();
        $days = (int) ($data['days'] ?? 90);

        $editingEntry = isset($data['edit'])
            ? $user->weightEntries()->findOrFail($data['edit'])
            : null;

        $entries = $user->weightEntries()
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->appends(['days' => $days]);

        $firstEntry = $user->weightEntries()
            ->where('recorded_on', '<', $today->addDay()->toDateString())
            ->orderBy('recorded_on')
            ->orderBy('id')
            ->first();

        $latestEntry = $user->weightEntries()
            ->where('recorded_on', '<', $today->addDay()->toDateString())
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->first();

        $startingWeight = $firstEntry
            ? (float) $firstEntry->weight_kg
            : null;

        $latestWeight = $latestEntry
            ? (float) $latestEntry->weight_kg
            : null;

        $weightChange = $startingWeight !== null && $latestWeight !== null
            ? $latestWeight - $startingWeight
            : null;

        $profile = $user->nutritionProfile()->first();

        $targetWeight = $profile?->target_weight_kg !== null
            ? (float) $profile->target_weight_kg
            : null;

        $remainingWeight = $targetWeight !== null && $latestWeight !== null
            ? abs($targetWeight - $latestWeight)
            : null;

        $chartEntries = $user->weightEntries()
            ->where(
                'recorded_on',
                '>=',
                $today->subDays($days - 1)->toDateString()
            )
            ->where('recorded_on', '<', $today->addDay()->toDateString())
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'weight_kg']);

        $chartPoints = $chartEntries
            ->map(fn (WeightEntry $entry) => [
                'date' => $entry->recorded_on->format('Y-m-d'),
                'weight' => WeightUnits::pounds($entry->weight_kg),
            ])
            ->values()
            ->all();

        $bmi = app(BodyMassIndex::class)->forProgress($user);

        return view('progress.index', compact(
            'bmi',
            'today',
            'days',
            'entries',
            'editingEntry',
            'firstEntry',
            'latestEntry',
            'startingWeight',
            'latestWeight',
            'weightChange',
            'targetWeight',
            'remainingWeight',
            'chartPoints'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($request, $data) {
            $user = $request->user();

            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            // Recheck under the lock to prevent duplicate daily entries.
            $this->assertDateAvailable(
                $request,
                $data['recorded_on']
            );

            $user->weightEntries()->create($data);
        });

        return redirect()
            ->route('progress.index')
            ->with('status', 'Your weight has been recorded.');
    }

    public function update(Request $request, int $entry)
    {
        $owned = $request->user()
            ->weightEntries()
            ->findOrFail($entry);

        $data = $this->validatedData($request, $owned);

        DB::transaction(function () use ($request, $owned, $data) {
            DB::table('users')
                ->where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $locked = $request->user()
                ->weightEntries()
                ->lockForUpdate()
                ->findOrFail($owned->id);

            $this->assertDateAvailable(
                $request,
                $data['recorded_on'],
                $locked->id
            );

            $locked->update($data);
        });

        return redirect()
            ->route('progress.index')
            ->with('status', 'Your weight entry has been updated.');
    }

    public function destroy(Request $request, int $entry)
    {
        $request->validate([
            'confirm_delete' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request, $entry) {
            DB::table('users')
                ->where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $request->user()
                ->weightEntries()
                ->findOrFail($entry)
                ->delete();
        });

        return redirect()
            ->route('progress.index')
            ->with('status', 'Your weight entry has been deleted.');
    }

    private function validatedData(
        Request $request,
        ?WeightEntry $entry = null
    ): array {
        $today = CarbonImmutable::now('Europe/London')->toDateString();

        $input = app(WeightInput::class)->kilograms($request, ['weight_kg' => $entry?->weight_kg], true);

        return Validator::make($input, [
            'recorded_on' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:1900-01-01',
                'before_or_equal:'.$today,
                Rule::unique('weight_entries', 'recorded_on')
                    ->where('user_id', $request->user()->id)
                    ->ignore($entry?->id),
            ],
            'weight_kg' => [
                'required',
                'numeric',
                'between:20,500',
                ...($request->exists('weight_lbs') ? [] : ['decimal:0,2']),
            ],
        ], [
            'recorded_on.unique' => 'You already recorded a weight for this date. '
                .'Edit that entry instead.',
        ])->validate();
    }

    private function assertDateAvailable(
        Request $request,
        string $date,
        ?int $exceptId = null
    ): void {
        $exists = $request->user()
            ->weightEntries()
            ->whereDate('recorded_on', $date)
            ->when(
                $exceptId !== null,
                fn ($query) => $query->where('id', '!=', $exceptId)
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'recorded_on' => 'You already recorded a weight for this date. '
                    .'Edit that entry instead.',
            ]);
        }
    }
}
