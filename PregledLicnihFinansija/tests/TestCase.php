<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before RefreshDatabase, so tests can't wipe a non-test database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $name = $app['config']->get('database.default');
        $db = $app['config']->get("database.connections.{$name}");

        $isSafe = $name === 'pgsql'
            && empty($db['url'])
            && in_array($db['host'], ['127.0.0.1', 'localhost', 'db'], true)
            && str_ends_with($db['database'], '_test');

        if (! $isSafe) {
            throw new RuntimeException('Tests stopped: they may only use a local Postgres database ending in "_test".');
        }

        return $app;
    }
}
