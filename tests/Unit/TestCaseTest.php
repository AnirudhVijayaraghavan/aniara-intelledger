<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestCase;

function databaseSafetyApplication(string $environment, mixed $connection, ?string $name = 'sqlite'): Application
{
    $app = new Application(dirname(__DIR__, 2));
    $app->instance('env', $environment);
    $app->instance('config', new Repository([
        'app' => ['env' => $environment],
        'database' => ['default' => $name, 'connections' => [$name ?? 'sqlite' => $connection]],
    ]));
    $app->bind('db', fn () => throw new LogicException('Database resolution is forbidden in these configuration-only tests.'));

    return $app;
}

test('the database guard accepts only resolved SQLite memory configurations without opening a connection', function (array|string $connection, string $name) {
    $app = databaseSafetyApplication('testing', $connection, $name);

    TestCase::ensureSafeTestDatabase($app);

    expect($app->resolved('db'))->toBeFalse();
})->with([
    'standard connection' => [['driver' => 'sqlite', 'database' => ':memory:'], 'sqlite'],
    'custom connection name' => [['driver' => 'sqlite', 'database' => ':memory:'], 'scratch'],
    'memory URL' => ['sqlite:///:memory:', 'sqlite'],
]);

test('the database guard rejects unsafe or incomplete configuration', function (string $environment, mixed $connection, ?string $name) {
    $app = databaseSafetyApplication($environment, $connection, $name);

    expect(fn () => TestCase::ensureSafeTestDatabase($app))->toThrow(
        RuntimeException::class,
        'Refusing to run tests: the effective environment must be testing and the resolved database must be SQLite :memory:. Check inherited environment variables, DB_URL, and cached configuration.',
    );

    expect($app->resolved('db'))->toBeFalse();
})->with([
    'local environment' => ['local', ['driver' => 'sqlite', 'database' => ':memory:'], 'sqlite'],
    'production environment' => ['production', ['driver' => 'sqlite', 'database' => ':memory:'], 'sqlite'],
    'MySQL named sqlite' => ['testing', ['driver' => 'mysql', 'database' => ':memory:'], 'sqlite'],
    'PostgreSQL' => ['testing', ['driver' => 'pgsql', 'database' => ':memory:'], 'pgsql'],
    'SQLite file' => ['testing', ['driver' => 'sqlite', 'database' => 'development.sqlite'], 'sqlite'],
    'missing default' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:'], null],
    'missing connection' => ['testing', null, 'sqlite'],
    'invalid connection type' => ['testing', false, 'sqlite'],
    'missing driver' => ['testing', ['database' => ':memory:'], 'sqlite'],
    'missing database' => ['testing', ['driver' => 'sqlite'], 'sqlite'],
    'URL overrides driver' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'mysql://localhost/development'], 'sqlite'],
    'URL overrides database' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'sqlite:///development.sqlite'], 'sqlite'],
    'URL query overrides driver' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'sqlite:///:memory:?driver=mysql'], 'sqlite'],
    'malformed URL' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'http://[invalid:port'], 'sqlite'],
    'read endpoint' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'read' => ['database' => 'development.sqlite']], 'sqlite'],
    'write endpoint' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'write' => ['database' => 'development.sqlite']], 'sqlite'],
    'direct endpoint' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'direct' => ['database' => 'development.sqlite']], 'sqlite'],
]);

/**
 * @param  array<string, string>  $overrides
 */
function withDatabaseSafetyEnvironment(array $overrides, Closure $callback): void
{
    $values = array_replace([
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
    ], $overrides);
    $previous = [];
    $container = Container::getInstance();

    foreach ($values as $name => $value) {
        $previous[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    try {
        $callback();
    } finally {
        foreach ($previous as $name => [$process, $env, $server]) {
            putenv($process === false ? $name : $name.'='.$process);

            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }

            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }

        Container::setInstance($container);
    }
}

test('inherited environment settings cannot make an unsafe database pass the guard', function (array $overrides) {
    withDatabaseSafetyEnvironment($overrides, function (): void {
        $app = databaseSafetyApplication('testing', []);
        $database = require dirname(__DIR__, 2).'/config/database.php';
        $app['config']->set('database', $database);
        $app->instance('env', env('APP_ENV'));

        expect(fn () => TestCase::ensureSafeTestDatabase($app))->toThrow(RuntimeException::class, 'Refusing to run tests:');
        expect($app->resolved('db'))->toBeFalse();
    });
})->with([
    'inherited environment' => [['APP_ENV' => 'local']],
    'inherited driver' => [['DB_CONNECTION' => 'mysql']],
    'inherited file' => [['DB_DATABASE' => 'development.sqlite']],
    'inherited URL' => [['DB_URL' => 'mysql://localhost/development']],
]);

test('inherited configuration cache paths cannot bypass the guard when environment variables are safe', function (string $environment, array $connection) {
    $cachePath = tempnam(sys_get_temp_dir(), 'intelledger-guard-');

    if ($cachePath === false) {
        throw new RuntimeException('Unable to create a temporary configuration cache fixture.');
    }

    $cachedConfig = [
        'app' => ['env' => $environment, 'timezone' => 'UTC'],
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => $connection]],
    ];
    file_put_contents($cachePath, '<?php return '.var_export($cachedConfig, true).';');

    try {
        withDatabaseSafetyEnvironment(['APP_CONFIG_CACHE' => $cachePath], function () use ($cachePath): void {
            $app = databaseSafetyApplication('testing', ['driver' => 'sqlite', 'database' => ':memory:']);
            $app->addAbsoluteCachePathPrefix($cachePath);

            (new LoadConfiguration)->bootstrap($app);

            expect($app['config_loaded_from_cache'])->toBeTrue();
            expect(fn () => TestCase::ensureSafeTestDatabase($app))->toThrow(RuntimeException::class, 'Refusing to run tests:');
            expect($app->resolved('db'))->toBeFalse();
        });
    } finally {
        unlink($cachePath);
    }
})->with([
    'cached local environment' => ['local', ['driver' => 'mysql', 'database' => 'development']],
    'cached URL under a testing environment' => ['testing', ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'mysql://localhost/development']],
]);
