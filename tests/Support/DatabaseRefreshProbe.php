<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseRefreshProbe extends TestCase
{
    use RefreshDatabase;

    public bool $refreshHookReached = false;

    public bool $migrationReached = false;

    protected array $connectionsToTransact = [null];

    public function bootDatabaseTraits(Application $app, array $connections = [null]): void
    {
        $this->app = $app;
        $this->connectionsToTransact = $connections;
        $this->setUpTraits();
    }

    // A subclass override must not bypass the shared safety guard.
    protected function beforeRefreshingDatabase(): void
    {
        $this->refreshHookReached = true;
    }

    protected function migrateDatabases()
    {
        $this->migrationReached = true;

        parent::migrateDatabases();
    }
}
