<?php

namespace App\Console\Commands;

use App\Jobs\GenerateWeeklyMealDraft;
use App\Models\WeeklyMealDraft;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MealPlanQueueStatusCommand extends Command
{
    protected $signature = 'meal-plans:queue-status';

    protected $description = 'Inspect meal-plan queue and draft status without consuming or changing jobs';

    public function handle(): int
    {
        $configuration = config('queue.connections.database');
        $connection = DB::connection($configuration['connection']);
        $table = $configuration['table'];
        $retryAfter = (int) $configuration['retry_after'];
        $now = now()->timestamp;
        $hasJobs = $connection->getSchemaBuilder()->hasTable($table);
        $jobs = $connection->table($table)->where('queue', 'meal-plans');
        $failedConfiguration = config('queue.failed');
        $failedConnection = DB::connection($failedConfiguration['database'] ?? null);
        $failedTable = $failedConfiguration['table'] ?? 'failed_jobs';
        $hasFailedJobs = in_array($failedConfiguration['driver'], ['database', 'database-uuids'], true) && $failedConnection->getSchemaBuilder()->hasTable($failedTable);
        $data = [
            'application_path' => base_path(),
            'php_binary' => PHP_BINARY,
            'php_version' => PHP_VERSION,
            'connection' => 'database',
            'database_connection' => $connection->getName(),
            'jobs_table' => $table,
            'queue' => 'meal-plans',
            'configured_default_queue' => $configuration['queue'],
            'retry_after_seconds' => $retryAfter,
            'job_timeout_seconds' => (new GenerateWeeklyMealDraft(0))->timeout,
            'pcntl_signal_available' => function_exists('pcntl_signal'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'configuration_cached' => app()->configurationIsCached(),
            'worker_script_present' => is_file(base_path('run-meal-worker.sh')),
            'jobs_table_present' => $hasJobs,
            'available_jobs' => $hasJobs ? (clone $jobs)->whereNull('reserved_at')->where('available_at', '<=', $now)->count() : null,
            'delayed_jobs' => $hasJobs ? (clone $jobs)->whereNull('reserved_at')->where('available_at', '>', $now)->count() : null,
            'reserved_jobs' => $hasJobs ? (clone $jobs)->where('reserved_at', '>', $now - $retryAfter)->count() : null,
            'expired_reservations' => $hasJobs ? (clone $jobs)->whereNotNull('reserved_at')->where('reserved_at', '<=', $now - $retryAfter)->count() : null,
            'queues' => $hasJobs ? $connection->table($table)->select('queue')->selectRaw('COUNT(*) as jobs')->groupBy('queue')->get()->all() : [],
            'recent_jobs' => $hasJobs ? (clone $jobs)->select('id', 'attempts', 'reserved_at', 'available_at', 'created_at')->orderByDesc('id')->limit(20)->get()->all() : [],
            'recent_failed_jobs' => $hasFailedJobs ? $failedConnection->table($failedTable)->where('queue', 'meal-plans')->select('id', 'connection', 'queue', 'failed_at')->orderByDesc('id')->limit(20)->get()->all() : [],
            'draft_status_counts' => WeeklyMealDraft::query()->select('status')->selectRaw('COUNT(*) as drafts')->groupBy('status')->get()->all(),
            'recent_drafts' => WeeklyMealDraft::query()->select('id', 'status', 'completed_types', 'created_at', 'updated_at')->orderByDesc('id')->limit(20)->get()->toArray(),
            'warnings' => $retryAfter <= (new GenerateWeeklyMealDraft(0))->timeout ? ['The database reservation expires before the meal job timeout. Check DB_QUEUE_RETRY_AFTER and rebuild cached configuration.'] : [],
        ];
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
