<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\ConfigurationUrlParser;
use Laravel\Fortify\Features;
use RuntimeException;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        self::ensureSafeTestDatabase($app);

        return $app;
    }

    /**
     * Check resolved configuration before Laravel applies database-refresh traits.
     * No database connection is opened, including when configuration is unsafe.
     */
    public static function ensureSafeTestDatabase(Application $app): void
    {
        $message = 'Refusing to run tests: the effective environment must be testing and the resolved database must be SQLite :memory:. Check inherited environment variables, DB_URL, and cached configuration.';
        $config = $app->make('config');
        $connectionName = $config->get('database.default');
        $connection = is_string($connectionName)
            ? $config->get('database.connections.'.$connectionName)
            : null;

        if (! $app->environment('testing') || (! is_array($connection) && ! is_string($connection))) {
            throw new RuntimeException($message);
        }

        try {
            $resolved = (new ConfigurationUrlParser)->parseConfiguration($connection);
        } catch (Throwable) {
            throw new RuntimeException($message);
        }

        if (($resolved['driver'] ?? null) !== 'sqlite'
            || ($resolved['database'] ?? null) !== ':memory:'
            || isset($resolved['read'])
            || isset($resolved['write'])
            || isset($resolved['direct'])) {
            throw new RuntimeException($message);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
