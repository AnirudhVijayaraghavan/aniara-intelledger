<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\DisposablePostgreSql;

/** @return array<string, string> */
function postgresOptInEnvironment(): array
{
    return ['APP_ENV' => 'testing', 'INTELLEDGER_PG_INTEGRATION' => DisposablePostgreSql::OPT_IN,
        'PG_TEST_HOST' => '127.0.0.1', 'PG_TEST_PORT' => '65432', 'PG_TEST_DATABASE' => DisposablePostgreSql::BOOTSTRAP,
        'PG_TEST_USERNAME' => DisposablePostgreSql::ROLE, 'PG_TEST_PASSWORD' => 'ci-only'];
}

/** @param array<string, mixed> $connection */
function postgresSafetyApplication(array $connection): Application
{
    $app = new Application(dirname(__DIR__, 2));
    $app->instance('env', 'testing');
    $app->instance('config', new Repository(['app' => ['env' => 'testing'],
        'database' => ['default' => 'pgsql', 'connections' => ['pgsql' => $connection]]]));
    $app->bind('db', fn () => throw new LogicException('Configuration-only tests must not open a database.'));

    return $app;
}

test('PostgreSQL opt in accepts only the dedicated configuration without resolving a database', function () {
    $expected = DisposablePostgreSql::configuration(postgresOptInEnvironment());
    $app = postgresSafetyApplication($expected);
    DisposablePostgreSql::assertConfiguration($app, $expected);
    expect($app->resolved('db'))->toBeFalse();
});

test('PostgreSQL raw preflight rejects unsafe inherited or incomplete targets', function (array $overrides) {
    $environment = array_replace(postgresOptInEnvironment(), $overrides);
    $environment = array_filter($environment, static fn (mixed $value): bool => $value !== null);
    expect(fn () => DisposablePostgreSql::configuration($environment))->toThrow(RuntimeException::class);
})->with([
    'no opt in' => [['INTELLEDGER_PG_INTEGRATION' => null]],
    'wrong opt in' => [['INTELLEDGER_PG_INTEGRATION' => 'yes']],
    'local environment' => [['APP_ENV' => 'local']],
    'production environment' => [['APP_ENV' => 'production']],
    'missing host' => [['PG_TEST_HOST' => null]],
    'remote host' => [['PG_TEST_HOST' => 'database.example']],
    'ambiguous DNS name' => [['PG_TEST_HOST' => 'localhost']],
    'multiple hosts' => [['PG_TEST_HOST' => '127.0.0.1,production']],
    'default development port' => [['PG_TEST_PORT' => '5432']],
    'development database' => [['PG_TEST_DATABASE' => 'intelledger']],
    'system database' => [['PG_TEST_DATABASE' => 'postgres']],
    'privileged generic role' => [['PG_TEST_USERNAME' => 'postgres']],
    'missing credential' => [['PG_TEST_PASSWORD' => '']],
    'inherited URL' => [['DB_URL' => 'postgresql://database.example/production']],
    'alternate URL' => [['DATABASE_URL' => 'postgresql://database.example/production']],
    'inherited driver' => [['DB_CONNECTION' => 'pgsql']],
    'inherited database' => [['DB_DATABASE' => 'development']],
    'cached configuration override' => [['APP_CONFIG_CACHE' => 'bootstrap/cache/config.php']],
    'route cache override' => [['APP_ROUTES_CACHE' => 'bootstrap/cache/routes.php']],
    'libpq service' => [['PGSERVICE' => 'development']],
    'libpq options' => [['PGOPTIONS' => '-c search_path=private']],
    'unexpected test URL' => [['PG_TEST_URL' => 'postgresql://database.example/production']],
]);

test('PostgreSQL effective configuration rejects endpoint bypasses before database access', function (array $overrides) {
    $expected = DisposablePostgreSql::configuration(postgresOptInEnvironment());
    $app = postgresSafetyApplication(array_replace($expected, $overrides));
    expect(fn () => DisposablePostgreSql::assertConfiguration($app, $expected))->toThrow(RuntimeException::class);
    expect($app->resolved('db'))->toBeFalse();
})->with([
    'wrong driver' => [['driver' => 'mysql']],
    'wrong host' => [['host' => 'production.example']],
    'wrong database' => [['database' => 'development']],
    'wrong port' => [['port' => '5432']],
    'wrong actor' => [['username' => 'postgres']],
    'URL override' => [['url' => 'postgresql://database.example/development']],
    'read replica' => [['read' => ['host' => 'development']]],
    'write endpoint' => [['write' => []]],
    'direct endpoint' => [['direct' => []]],
    'pooled connection' => [['pooled' => true]],
    'connection database alias' => [['connect_via_database' => 'development']],
    'connection port alias' => [['connect_via_port' => 5432]],
    'PDO options' => [['options' => []]],
    'unexpected schema' => [['search_path' => 'production']],
]);

test('PostgreSQL safety rejects cached configuration and conflicting environment sources', function () {
    $key = 'APP_CONFIG_CACHE';
    $previous = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
    $path = tempnam(sys_get_temp_dir(), 'intelledger-pg-cache-');
    if ($path === false) {
        throw new RuntimeException('Unable to create an isolated cache fixture.');
    }
    try {
        file_put_contents($path, '<?php return [];');
        putenv($key.'='.$path);
        $_ENV[$key] = $path;
        $_SERVER[$key] = $path;
        $expected = DisposablePostgreSql::configuration(postgresOptInEnvironment());
        $app = postgresSafetyApplication($expected);
        if (preg_match('/\A[A-Za-z]:/', $path, $drive) === 1) {
            $app->addAbsoluteCachePathPrefix($drive[0]);
        }
        expect($app->configurationIsCached())->toBeTrue();
        expect(fn () => DisposablePostgreSql::assertConfiguration($app, $expected))->toThrow(RuntimeException::class);
        expect($app->resolved('db'))->toBeFalse();
        $_SERVER[$key] = 'a-conflicting-cache';
        expect(fn () => DisposablePostgreSql::environment())->toThrow(RuntimeException::class);
    } finally {
        putenv($previous[0] === false ? $key : $key.'='.$previous[0]);
        foreach (['_ENV' => $previous[1], '_SERVER' => $previous[2]] as $source => $value) {
            if ($value === null) {
                unset($GLOBALS[$source][$key]);
            } else {
                $GLOBALS[$source][$key] = $value;
            }
        }
        unlink($path);
    }
});

test('the PostgreSQL entry point refuses inherited development URLs before bootstrapping Laravel', function () {
    $environment = [];
    foreach (array_keys(getenv()) as $key) {
        if (preg_match('/\A(?:DB_.*|DATABASE_URL|PG.*|APP_.*_CACHE)\z/', $key)) {
            $environment[$key] = false;
        }
    }
    $environment = array_replace($environment, postgresOptInEnvironment(), ['DB_URL' => 'postgresql://database.example/development']);
    $process = new Process([PHP_BINARY, 'tests/PostgreSqlIntegration.php'], dirname(__DIR__, 2), $environment);
    $process->setTimeout(15);
    $process->run();
    expect($process->getExitCode())->toBe(1);
    expect($process->getErrorOutput())->toContain('configuration preflight')->not->toContain('database.example');
    $report = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    expect($report['tests'])->toBe(0);
});

test('private bootstrap preparation refuses hidden environment files before changing configuration', function () {
    $directory = sys_get_temp_dir().'/intelledger-pg-env-'.bin2hex(random_bytes(12));
    mkdir($directory, 0700);
    try {
        file_put_contents($directory.'/.env', 'APP_ENV=production');
        $expected = DisposablePostgreSql::configuration(postgresOptInEnvironment());
        $app = postgresSafetyApplication($expected);
        $lease = new DisposablePostgreSql($expected);
        expect(fn () => $lease->prepare($app, $directory))->toThrow(RuntimeException::class);
        expect($app->resolved('db'))->toBeFalse();
    } finally {
        unlink($directory.'/.env');
        rmdir($directory);
    }
});

test('PostgreSQL CI uses only the pinned disposable service and remains outside the local quality graph', function () {
    $root = dirname(__DIR__, 2);
    $workflow = Yaml::parseFile($root.'/.github/workflows/tests.yml');
    $job = $workflow['jobs']['postgres-integration'];
    expect($workflow['jobs']['ci']['needs'])->toContain('postgres-integration');
    expect($job)->not->toHaveKeys(['if', 'continue-on-error', 'permissions']);
    expect($job['timeout-minutes'])->toBe(15);
    expect(array_keys($job['services']))->toBe(['postgres']);
    $postgres = $job['services']['postgres'];
    expect($postgres['image'])->toBe('postgres:18.6-bookworm@sha256:5c4783a8cc3f0074912164f94dfa1be4e043438f0f707cf968d7af9a0d31a2c8');
    expect($postgres['ports'])->toBe(['65432:5432']);
    expect($postgres['env'])->toBe(['POSTGRES_DB' => DisposablePostgreSql::BOOTSTRAP,
        'POSTGRES_USER' => DisposablePostgreSql::ROLE, 'POSTGRES_PASSWORD' => 'ci-only']);
    expect($postgres['options'])->toBe('--health-cmd "pg_isready -U intelledger_ci -d intelledger_ci_bootstrap" --health-interval 5s --health-timeout 5s --health-retries 10');
    expect($postgres)->not->toHaveKey('volumes');
    expect($job['env'])->toBe(postgresOptInEnvironment());
    expect(array_column($job['steps'], 'run'))->toContain('composer test:isolation', 'composer postgres:guard', 'composer test:postgres', 'git diff --exit-code HEAD');
    $composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($composer['scripts']['test:postgres'])->toBe(['Composer\\Config::disableProcessTimeout', '@php -d memory_limit=256M tests/PostgreSqlIntegration.php']);
    expect($composer['scripts']['ci:check'])->toBe(['@quality:backend', '@quality:frontend', '@test:isolated']);
});

test('PostgreSQL worker capabilities reject ambiguous or unrelated targets without a connection', function (array $overrides) {
    $receipt = array_replace(['database' => 'intelledger_ci_'.str_repeat('a', 24), 'oid' => 123,
        'nonce' => str_repeat('b', 32), 'parent_pid' => 456], $overrides);
    expect(fn () => DisposablePostgreSql::validateWorkerReceipt($receipt))->toThrow(RuntimeException::class);
})->with([
    'development target' => [['database' => 'intelledger']],
    'bootstrap target' => [['database' => DisposablePostgreSql::BOOTSTRAP]],
    'SQL metacharacters' => [['database' => 'intelledger_ci_";drop database postgres;']],
    'OID string' => [['oid' => '123']],
    'missing OID' => [['oid' => null]],
    'negative OID' => [['oid' => -1]],
    'missing parent' => [['parent_pid' => null]],
    'PID string' => [['parent_pid' => '456']],
    'invalid nonce' => [['nonce' => 'unknown']],
    'extra endpoint' => [['host' => 'production.example']],
]);

test('PostgreSQL worker receipt shape is checked before preparing Laravel', function () {
    $expected = DisposablePostgreSql::configuration(postgresOptInEnvironment());
    $app = postgresSafetyApplication($expected);
    $lease = new DisposablePostgreSql($expected);
    $receipt = ['database' => 'postgres', 'oid' => 1, 'nonce' => str_repeat('b', 32), 'parent_pid' => 456];
    expect(fn () => $lease->prepare($app, '/does-not-exist', $receipt))->toThrow(RuntimeException::class);
    expect($app->resolved('db'))->toBeFalse();
});

test('valid worker capabilities need exact owned identities but cannot prove server ownership offline', function () {
    DisposablePostgreSql::validateWorkerReceipt(['database' => 'intelledger_ci_'.str_repeat('a', 24), 'oid' => 123,
        'nonce' => str_repeat('b', 32), 'parent_pid' => 456]);
    expect(true)->toBeTrue();
});

test('private PostgreSQL cache cleanup refuses unrelated directories', function () {
    $directory = sys_get_temp_dir().'/pg-environment-'.bin2hex(random_bytes(12));
    mkdir($directory, 0700);
    try {
        file_put_contents($directory.'/config.php', 'synthetic');
        expect(fn () => DisposablePostgreSql::clearEnvironmentDirectory($directory))->toThrow(RuntimeException::class);
        expect(file_get_contents($directory.'/config.php'))->toBe('synthetic');
    } finally {
        unlink($directory.'/config.php');
        rmdir($directory);
    }
});

test('private PostgreSQL cache cleanup removes only verified bootstrap files', function () {
    $directory = dirname(__DIR__, 2).'/.phpunit.cache/pg-environment-'.bin2hex(random_bytes(12));
    $names = ['config.php', 'routes.php', 'events.php', 'services.php', 'packages.php'];
    try {
        expect(mkdir($directory, 0700, true))->toBeTrue();
        foreach ($names as $name) {
            expect(file_put_contents($directory.'/'.$name, 'synthetic'))->not->toBeFalse();
        }
        DisposablePostgreSql::clearEnvironmentDirectory($directory);
        expect(is_dir($directory))->toBeFalse();
        DisposablePostgreSql::clearEnvironmentDirectory($directory);
        DisposablePostgreSql::clearEnvironmentDirectory(null);
    } finally {
        foreach ($names as $name) {
            if (is_file($directory.'/'.$name)) {
                unlink($directory.'/'.$name);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('private PostgreSQL cache cleanup refuses unexpected artifacts and retains evidence', function (string $name, bool $nested) {
    $directory = dirname(__DIR__, 2).'/.phpunit.cache/pg-environment-'.bin2hex(random_bytes(12));
    try {
        expect(mkdir($directory, 0700, true))->toBeTrue();
        expect(file_put_contents($directory.'/config.php', 'preserved bootstrap evidence'))->not->toBeFalse();
        if ($nested) {
            expect(mkdir($directory.'/'.$name, 0700))->toBeTrue();
            expect(file_put_contents($directory.'/'.$name.'/sentinel', 'synthetic'))->not->toBeFalse();
        } else {
            expect(file_put_contents($directory.'/'.$name, 'synthetic'))->not->toBeFalse();
        }
        expect(fn () => DisposablePostgreSql::clearEnvironmentDirectory($directory))->toThrow(RuntimeException::class);
        expect(file_get_contents($directory.'/config.php'))->toBe('preserved bootstrap evidence');
        expect(file_get_contents($directory.'/'.$name.($nested ? '/sentinel' : '')))->toBe('synthetic');
    } finally {
        if (is_file($directory.'/config.php')) {
            unlink($directory.'/config.php');
        }
        $artifact = $directory.'/'.$name.($nested ? '/sentinel' : '');
        if (is_file($artifact)) {
            unlink($artifact);
        }
        if ($nested && is_dir($directory.'/'.$name)) {
            rmdir($directory.'/'.$name);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
})->with([
    'unexpected file' => ['unexpected.txt', false],
    'unexpected directory' => ['unexpected', true],
    'allowed filename is a directory' => ['routes.php', true],
]);
