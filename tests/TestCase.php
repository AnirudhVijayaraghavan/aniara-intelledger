<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = parent::createApplication();

        // Keep tests isolated from the local application database, even when config is cached.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.url', null);
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
