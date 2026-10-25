<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestDatabaseSafety
{
    public static function assertSafe(Application $app, array $connections = []): void
    {
        // Environment variables cannot override a previously cached configuration.
        if ($app->configurationIsCached()) {
            throw new RuntimeException('Database tests refuse cached configuration. Remove the configuration cache before running tests.');
        }

        if (! $app->environment('testing') || $app['config']->get('app.env') !== 'testing') {
            throw new RuntimeException('Database tests require the testing environment.');
        }

        $database = $app->make('db');
        $connections[] = $database->getDefaultConnection();

        foreach (array_unique($connections) as $name) {
            // Resolves URL overrides and existing connection instances without
            // opening a PDO connection to a potentially persistent database.
            $connection = $database->connection($name);

            if ($connection->getDriverName() !== 'sqlite'
                || $connection->getDatabaseName() !== ':memory:'
                || $connection->getConfig('read')
                || $connection->getConfig('write')) {
                throw new RuntimeException('Database tests require every selected connection to resolve to SQLite :memory:. No migrations were run.');
            }
        }
    }
}
