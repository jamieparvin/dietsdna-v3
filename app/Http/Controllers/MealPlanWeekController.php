<?php

namespace App\Http\Controllers;

use App\Models\WeeklyMealDraft;
use App\Services\Meals\MealPlanWeekState;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class MealPlanWeekController extends Controller
{
    public function create(Request $request, MealPlanWeekState $weeks)
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($data['date'] ?? now('Europe/London')->toDateString(), 'Europe/London')->startOfWeek(1);
        $state = $weeks->capture($request->user()->id, $start);
        $count = $state['entries']->count();
        $token = Crypt::encryptString(json_encode([
            'action' => 'clear_week', 'user_id' => $request->user()->id,
            'start_date' => $start->toDateString(), 'fingerprint' => $state['fingerprint'],
        ], JSON_THROW_ON_ERROR));

        return view('meal-plan.clear-week', compact('start', 'count', 'token'));
    }

    public function store(Request $request, MealPlanWeekState $weeks)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:5000'],
            'clear_confirmed' => ['required', 'accepted'],
        ]);
        try {
            $intent = json_decode(Crypt::decryptString($data['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            $this->fail('This confirmation is no longer valid. Open Clear this week again.');
        }
        if (! is_array($intent) || ($intent['action'] ?? null) !== 'clear_week'
            || ! is_string($intent['start_date'] ?? null) || ! is_string($intent['fingerprint'] ?? null)) {
            $this->fail('This confirmation is no longer valid. Open Clear this week again.');
        }
        abort_unless((int) ($intent['user_id'] ?? 0) === (int) $request->user()->id, 404);
        // The date and owner come from authenticated encrypted confirmation data.
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $intent['start_date'], 'Europe/London');
        if (! $start || $start->dayOfWeekIso !== 1) {
            $this->fail('Choose a valid week to clear.');
        }

        $removed = DB::transaction(function () use ($request, $weeks, $start, $intent) {
            $userId = $request->user()->id;
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $state = $weeks->capture($userId, $start);
            if (! hash_equals($state['fingerprint'], $intent['fingerprint'])) {
                $this->fail('Your meal plan changed after this confirmation opened. Reload Clear this week and review it again. Nothing was removed.');
            }
            $removed = $weeks->query($userId, $start)->delete();

            // An old background job/ready preview cannot repopulate the cleared week.
            WeeklyMealDraft::query()->where('user_id', $userId)->whereDate('start_date', $start->toDateString())
                ->whereIn('status', ['queued', 'generating', 'ready'])->update(['status' => 'cancelled', 'updated_at' => now()]);
            return $removed;
        });

        return redirect()->route('meal-plan.index', ['date' => $start->toDateString()])
            ->with('status', $removed.' planned meals removed from this week. Your Saved meals are still available.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['week' => $message]);
    }
}
