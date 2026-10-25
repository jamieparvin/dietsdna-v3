<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Http;
use PDO;
use RuntimeException;
use Tests\Support\DatabaseRefreshProbe;
use Tests\Support\TestDatabaseSafety;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_all_phpunit_configurations_force_disposable_database_settings(): void
    {
        $files = glob(base_path('phpunit*.xml'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $xml = simplexml_load_file($file);
            foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $name => $value) {
                $entries = $xml->xpath('/phpunit/php/env[@name="'.$name.'"]');
                $this->assertCount(1, $entries, basename($file).': '.$name);
                $this->assertSame($value, (string) $entries[0]['value']);
                $this->assertSame('true', (string) $entries[0]['force']);
            }
        }
    }

    public function test_resolved_in_memory_sqlite_is_allowed(): void
    {
        TestDatabaseSafety::assertSafe($this->app);

        $connection = $this->app['db']->connection();
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
        $this->assertSame('', $connection->select('PRAGMA database_list')[0]->file);
    }

    public function test_file_backed_url_is_rejected_before_refresh_hooks_or_schema_changes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dietsdna-isolation-');
        $this->assertNotFalse($path);
        $pdo = new PDO('sqlite:'.$path);
        $pdo->exec('CREATE TABLE isolation_sentinel (value TEXT NOT NULL)');
        $pdo->exec("INSERT INTO isolation_sentinel VALUES ('keep this row')");
        $schema = $pdo->query('SELECT name, sql FROM sqlite_master ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $pdo = null;
        $hash = hash_file('sha256', $path);
        $original = config('database.connections.sqlite');
        $migrated = RefreshDatabaseState::$migrated;
        $inMemoryConnections = RefreshDatabaseState::$inMemoryConnections;
        $probe = new DatabaseRefreshProbe('test_file_backed_url');

        try {
            // Keep the misleading raw :memory: setting: Laravel's URL resolution
            // is what points the actual connection at this disposable file.
            config(['database.connections.sqlite.url' => 'sqlite://localhost?database='.rawurlencode($path)]);
            $this->app['db']->purge('sqlite');
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
            $this->assertSame($path, $this->app['db']->connection()->getDatabaseName());
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];

            try {
                $probe->bootDatabaseTraits($this->app);
                $this->fail('A persistent resolved connection was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('resolve to SQLite :memory:', $exception->getMessage());
            }

            $this->assertFalse($probe->refreshHookReached);
            $this->assertFalse($probe->migrationReached);
            $this->assertSame($hash, hash_file('sha256', $path));
            $pdo = new PDO('sqlite:'.$path);
            $this->assertSame($schema, $pdo->query('SELECT name, sql FROM sqlite_master ORDER BY name')->fetchAll(PDO::FETCH_ASSOC));
            $this->assertSame(['keep this row'], $pdo->query('SELECT value FROM isolation_sentinel')->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $pdo = null;
            $this->app['db']->purge('sqlite');
            config(['database.connections.sqlite' => $original]);
            RefreshDatabaseState::$migrated = $migrated;
            RefreshDatabaseState::$inMemoryConnections = $inMemoryConnections;
            unlink($path);
        }
    }

    public function test_cached_development_configuration_is_rejected_before_refresh(): void
    {
        $probe = new DatabaseRefreshProbe('test_cached_configuration');
        $bootstrapPath = $this->app->bootstrapPath();
        $directory = sys_get_temp_dir().'/dietsdna-config-'.bin2hex(random_bytes(8));
        mkdir($directory.'/cache', 0700, true);
        file_put_contents($directory.'/cache/config.php', "<?php return ['app' => ['env' => 'local']];");

        try {
            // Detect a real cache file even though PHPUnit forced testing values.
            $this->app->useBootstrapPath($directory);
            $this->app->forgetInstance('config_loaded_from_cache');
            $this->assertTrue($this->app->configurationIsCached());
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('refuse cached configuration');
            $probe->bootDatabaseTraits($this->app);
        } finally {
            $this->assertFalse($probe->refreshHookReached);
            $this->assertFalse($probe->migrationReached);
            $this->app->useBootstrapPath($bootstrapPath);
            $this->app->instance('config_loaded_from_cache', false);
            unlink($directory.'/cache/config.php');
            rmdir($directory.'/cache');
            rmdir($directory);
        }
    }

    public function test_non_testing_environment_is_rejected(): void
    {
        config(['app.env' => 'local']);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('require the testing environment');
            TestDatabaseSafety::assertSafe($this->app);
        } finally {
            config(['app.env' => 'testing']);
        }
    }

    public function test_additional_transaction_connections_are_checked(): void
    {
        config(['database.connections.unsafe' => ['driver' => 'sqlite', 'database' => 'not-an-in-memory-database']]);
        $probe = new DatabaseRefreshProbe('test_additional_connection');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('resolve to SQLite :memory:');
            $probe->bootDatabaseTraits($this->app, ['sqlite', 'unsafe']);
        } finally {
            $this->assertFalse($probe->refreshHookReached);
            $this->assertFalse($probe->migrationReached);
            $this->app['db']->purge('unsafe');
        }
    }
}
