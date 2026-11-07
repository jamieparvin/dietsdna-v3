<?php

namespace Tests\Feature;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use App\Services\Meals\WeeklyMealPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MealPlanQueueTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): WeeklyMealDraft
    {
        return WeeklyMealDraft::create(['user_id' => User::factory()->create()->id, 'start_date' => '2026-11-09', 'status' => 'queued', 'source_signature' => 'queue-fixture', 'meal_count' => 2, 'slots' => ['lunch' => 'Lunch', 'dinner' => 'Dinner']]);
    }

    private function runOnceWorker(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'meal-plans', '--once' => true, '--sleep' => 0, '--tries' => 1])->assertExitCode(0);
    }

    public function test_once_worker_consumes_the_named_database_queue_claims_the_draft_and_does_not_regenerate_on_redelivery(): void
    {
        Http::preventStrayRequests();
        $draft = $this->draft();
        $this->mock(WeeklyMealPlanner::class, function ($mock) use ($draft): void {
            $mock->shouldReceive('generate')->once()->andReturnUsing(function ($claimed) use ($draft): void {
                $this->assertSame($draft->id, $claimed->id);
                $this->assertSame('generating', $claimed->fresh()->status);
                $claimed->update(['status' => 'ready']);
            });
        });
        GenerateWeeklyMealDraft::dispatch($draft->id);
        $this->assertDatabaseHas('jobs', ['queue' => 'meal-plans', 'attempts' => 0, 'reserved_at' => null]);
        $this->assertSame('queued', $draft->fresh()->status);
        $this->runOnceWorker();
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        GenerateWeeklyMealDraft::dispatch($draft->id);
        $this->runOnceWorker();
        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_generation_failure_is_terminal_and_is_not_retried_by_the_once_worker(): void
    {
        Http::preventStrayRequests();
        $draft = $this->draft();
        $this->mock(WeeklyMealPlanner::class, function ($mock): void {
            $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('Simulated provider failure'));
        });
        GenerateWeeklyMealDraft::dispatch($draft->id);
        $this->runOnceWorker();
        $this->assertSame('failed', $draft->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->runOnceWorker();
        $this->assertDatabaseCount('failed_jobs', 1);
        Http::assertNothingSent();
    }

    public function test_read_only_diagnostics_distinguish_waiting_reserved_and_expired_jobs_without_consuming_them(): void
    {
        Http::preventStrayRequests();
        $this->freezeTime();
        config(['queue.connections.database.retry_after' => 600]);
        $draft = $this->draft();
        GenerateWeeklyMealDraft::dispatch($draft->id);
        $first = DB::table('jobs')->first();
        DB::table('jobs')->insert(['queue' => 'meal-plans', 'payload' => $first->payload, 'attempts' => 1, 'reserved_at' => now()->timestamp - 100, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        DB::table('jobs')->insert(['queue' => 'meal-plans', 'payload' => $first->payload, 'attempts' => 1, 'reserved_at' => now()->timestamp - 601, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $before = DB::table('jobs')->orderBy('id')->get()->toJson();
        $this->assertSame(0, Artisan::call('meal-plans:queue-status'));
        $status = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $status['available_jobs']);
        $this->assertSame(1, $status['reserved_jobs']);
        $this->assertSame(1, $status['expired_reservations']);
        $this->assertSame(600, $status['retry_after_seconds']);
        $this->assertSame('queued', $status['recent_drafts'][0]['status']);
        $this->assertArrayNotHasKey('payload', $status['recent_jobs'][0]);
        $this->assertSame($before, DB::table('jobs')->orderBy('id')->get()->toJson());
        $this->assertSame('queued', $draft->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_active_reservation_is_not_reclaimed_after_the_former_ninety_second_default(): void
    {
        Http::preventStrayRequests();
        $draft = $this->draft();
        $this->assertGreaterThan((new GenerateWeeklyMealDraft($draft->id))->timeout, (int) config('queue.connections.database.retry_after'));
        $this->mock(WeeklyMealPlanner::class, fn ($mock) => $mock->shouldNotReceive('generate'));
        GenerateWeeklyMealDraft::dispatch($draft->id);
        DB::table('jobs')->update(['attempts' => 1, 'reserved_at' => now()->timestamp - 100]);
        $this->runOnceWorker();
        $this->assertSame('queued', $draft->fresh()->status);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        Http::assertNothingSent();
    }

    public function test_diagnostics_warn_when_live_configuration_still_has_the_old_short_reservation(): void
    {
        config(['queue.connections.database.retry_after' => 90]);
        $this->assertSame(0, Artisan::call('meal-plans:queue-status'));
        $status = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(90, $status['retry_after_seconds']);
        $this->assertCount(1, $status['warnings']);
        $this->assertStringContainsString('reservation expires before', $status['warnings'][0]);
        $this->assertDatabaseCount('jobs', 0);
    }
}
