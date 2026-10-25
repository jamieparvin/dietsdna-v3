<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    final protected function setUpTraits()
    {
        $connections = method_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact()
            : [];

        // Run before RefreshDatabase (including any subclass refresh hooks).
        TestDatabaseSafety::assertSafe($this->app, $connections);

        return parent::setUpTraits();
    }
}
