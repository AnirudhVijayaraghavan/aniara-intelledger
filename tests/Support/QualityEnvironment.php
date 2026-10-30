<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use LogicException;
use Tests\TestCase;

final class QualityEnvironment
{
    /**
     * Composer callbacks affect only this command and its children. Nothing is
     * written to environment files or development configuration caches.
     */
    public static function configure(): void
    {
        require_once dirname(__DIR__, 2).'/vendor/autoload.php';

        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_URL' => 'http://localhost',
            'APP_CONFIG_CACHE' => 'bootstrap/cache/config.testing.php',
            'APP_ROUTES_CACHE' => 'bootstrap/cache/routes.testing.php',
            'APP_EVENTS_CACHE' => 'bootstrap/cache/events.testing.php',
            'APP_SERVICES_CACHE' => 'bootstrap/cache/services.testing.php',
            'APP_PACKAGES_CACHE' => 'bootstrap/cache/packages.testing.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => 'sqlite:///:memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'BROADCAST_CONNECTION' => 'null',
            'MAIL_MAILER' => 'array',
            'LOG_CHANNEL' => 'stderr',
            'BCRYPT_ROUNDS' => '4',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
            'NODE_OPTIONS' => '--max-old-space-size=512 --max-semi-space-size=4',
            'ROLLDOWN_WORKER_THREADS' => '1',
            'RAYON_NUM_THREADS' => '1',
            'GOMAXPROCS' => '2',
        ];
        foreach ($environment as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        $previousContainer = Container::getInstance();
        $previousTimezone = date_default_timezone_get();
        try {
            $app = new Application(dirname(__DIR__, 2));
            $app->bind('db', fn () => throw new LogicException('Quality preflight must not resolve a database connection.'));
            $app->bootstrapWith([LoadEnvironmentVariables::class, LoadConfiguration::class]);
            TestCase::ensureSafeTestDatabase($app);
        } finally {
            Container::setInstance($previousContainer);
            date_default_timezone_set($previousTimezone);
        }

        echo 'Quality isolation verified: testing / sqlite / :memory: (configuration only).'.PHP_EOL;
    }
}
