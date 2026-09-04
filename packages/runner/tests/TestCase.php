<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * QueryWatcherTest and LogWatcherTest need a real, resolvable DatabaseManager connection and
     * Dispatcher (see the moved Watchers/*.php, which call $app->make(DatabaseManager::class) /
     * $app->make(Dispatcher::class) themselves) to prove the watchers bind to Illuminate's actual
     * contracts, not a Mockery double standing in for them.
     *
     * @param  Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app->make(Repository::class)->set('database.default', 'testing');
        $app->make(Repository::class)->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
