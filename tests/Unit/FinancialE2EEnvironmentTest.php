<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\DisposablePostgreSql;
use Tests\Support\FinancialE2EEnvironment as E2E;

/** @return array<string, string> */
function financialE2EOptIn(): array
{
    return ['APP_ENV' => 'testing', 'INTELLEDGER_PG_INTEGRATION' => DisposablePostgreSql::OPT_IN,
        'PG_TEST_HOST' => '127.0.0.1', 'PG_TEST_PORT' => DisposablePostgreSql::PORT,
        'PG_TEST_DATABASE' => DisposablePostgreSql::BOOTSTRAP, 'PG_TEST_USERNAME' => DisposablePostgreSql::ROLE,
        'PG_TEST_PASSWORD' => 'ci-only', 'INTELLEDGER_FINANCIAL_E2E' => E2E::OPT_IN];
}

test('E2E accepts explicit invocation values and allows context only for a worker', function () {
    E2E::assertInvocation(financialE2EOptIn());
    $worker = array_replace(financialE2EOptIn(), ['INTELLEDGER_E2E_CONTEXT' => '{}']);
    E2E::assertInvocation($worker, worker: true);
    expect(fn () => E2E::assertInvocation($worker))->toThrow(RuntimeException::class);
});

test('interrupting the E2E supervisor releases its owned child listener', function () {
    $reservation = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    expect(is_resource($reservation))->toBeTrue();
    $address = 'tcp://'.stream_socket_get_name($reservation, false);
    fclose($reservation);
    $supervisor = new Process([PHP_BINARY, '-r', <<<'PHP'
require $argv[1].'/vendor/autoload.php';
Tests\Support\FinancialE2EEnvironment::handleTerminationSignals();
$server = new Symfony\Component\Process\Process([PHP_BINARY, '-r',
    '$socket = stream_socket_server($argv[1]); if ($socket === false) { exit(1); } fwrite(STDOUT, "synthetic listener ready"); $client = @stream_socket_accept($socket, 10); if (is_resource($client)) { fclose($client); } fclose($socket);',
    $argv[2]]);
$server->setTimeout(12);
try {
    $server->run(static function (string $type, string $output): void { fwrite(STDOUT, $output); });
} finally {
    $server->stop(1);
}
PHP, dirname(__DIR__, 2), $address]);
    $supervisor->setTimeout(15);
    try {
        $supervisor->start();
        expect($supervisor->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'synthetic listener ready')))->toBeTrue();
        $supervisor->stop(2);
        $listener = @stream_socket_server($address, $code, $message);
        try {
            expect(is_resource($listener))->toBeTrue();
        } finally {
            if (is_resource($listener)) {
                fclose($listener);
            }
        }
    } finally {
        if ($supervisor->isRunning()) {
            $supervisor->stop(2);
        }
    }
});

test('E2E requires an additional explicit opt in and rejects inherited HTTP overrides', function (array $overrides) {
    expect(fn () => E2E::assertInvocation(array_replace(financialE2EOptIn(), $overrides)))->toThrow(RuntimeException::class);
})->with([
    'missing opt in' => [['INTELLEDGER_FINANCIAL_E2E' => '']],
    'wrong opt in' => [['INTELLEDGER_FINANCIAL_E2E' => 'yes']],
    'console override would bypass real CSRF' => [['APP_RUNNING_IN_CONSOLE' => 'true']],
    'unbounded HTTP workers' => [['PHP_CLI_SERVER_WORKERS' => '8']],
    'inherited storage' => [['LARAVEL_STORAGE_PATH' => '/development/storage']],
    'inherited compiled views' => [['VIEW_COMPILED_PATH' => '/development/views']],
    'remote asset server' => [['ASSET_URL' => 'https://example.invalid']],
    'stale owner context' => [['INTELLEDGER_E2E_CONTEXT' => '{}']],
]);

test('E2E preserves the original PostgreSQL raw target refusals before application bootstrap', function (array $override) {
    $environment = array_replace(financialE2EOptIn(), $override);
    unset($environment['INTELLEDGER_FINANCIAL_E2E']);
    expect(fn () => DisposablePostgreSql::configuration($environment))->toThrow(RuntimeException::class);
})->with([
    'development driver' => [['DB_CONNECTION' => 'pgsql']],
    'inherited URL' => [['DB_URL' => 'postgresql://example.invalid/development']],
    'alternate URL' => [['DATABASE_URL' => 'postgresql://example.invalid/development']],
    'cached configuration' => [['APP_CONFIG_CACHE' => 'bootstrap/cache/config.php']],
    'remote host' => [['PG_TEST_HOST' => 'example.invalid']],
    'development port' => [['PG_TEST_PORT' => '5432']],
    'development database' => [['PG_TEST_DATABASE' => 'intelledger']],
    'wrong role' => [['PG_TEST_USERNAME' => 'postgres']],
    'missing credential' => [['PG_TEST_PASSWORD' => '']],
    'production environment' => [['APP_ENV' => 'production']],
]);

test('E2E refuses an occupied port without replacing or stopping its listener', function () {
    $socket = @stream_socket_server('tcp://127.0.0.1:4182', $code, $message);
    try {
        expect(fn () => E2E::assertPortFree())->toThrow(RuntimeException::class);
        if (is_resource($socket)) {
            expect(stream_socket_get_name($socket, false))->toBe('127.0.0.1:4182');
        }
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
});

test('E2E context rejects malformed capabilities and unrelated private directories', function (array $override) {
    $context = array_replace(['receipt' => ['database' => 'intelledger_ci_'.str_repeat('a', 24),
        'oid' => 10, 'nonce' => str_repeat('a', 32), 'parent_pid' => 100],
        'run' => dirname(__DIR__, 2).'/storage', 'key' => 'base64:'.base64_encode(str_repeat('a', 32))], $override);
    expect(fn () => E2E::context(json_encode($context, JSON_THROW_ON_ERROR)))->toThrow(RuntimeException::class);
})->with([
    'unrelated directory' => [[]],
    'development receipt' => [['receipt' => ['database' => 'development']]],
    'invalid key' => [['key' => 'base64:short']],
    'additional capability field' => [['extra' => 'unexpected']],
]);

test('the actual E2E entry point fails before provisioning when inherited configuration is unsafe', function () {
    $environment = [];
    foreach (array_keys(getenv()) as $key) {
        if (preg_match('/\A(?:APP_.*|DB_.*|DATABASE_URL|PG.*|INTELLEDGER_.*|ASSET_URL|LARAVEL_STORAGE_PATH|PHP_CLI_SERVER_WORKERS)\z/', $key)) {
            $environment[$key] = false;
        }
    }
    $process = new Process([PHP_BINARY, 'tests/FinancialE2E.php'], dirname(__DIR__, 2), array_replace($environment,
        financialE2EOptIn(), ['DB_URL' => 'postgresql://example.invalid/production']));
    $process->setTimeout(10);
    $process->run();
    expect($process->getExitCode())->toBe(1);
    expect($process->getErrorOutput())->toContain('configuration preflight')->not->toContain('postgresql://');
    expect($process->getOutput())->toContain('"result":"failed"');
});

test('E2E cleans recorded bootstrap files left by an interrupted child without a database', function () {
    $cache = dirname(__DIR__, 2).'/.phpunit.cache';
    $run = $cache.'/e2e-run-'.bin2hex(random_bytes(12));
    $directory = $cache.'/pg-environment-'.bin2hex(random_bytes(12));
    try {
        expect(mkdir($run, 0700, true))->toBeTrue();
        expect(mkdir($directory, 0700))->toBeTrue();
        expect(file_put_contents($directory.'/packages.php', '<?php return [];'))->not->toBeFalse();
        expect(file_put_contents($run.'/bootstrap-directories.jsonl', json_encode($directory, JSON_THROW_ON_ERROR)."\n"))->not->toBeFalse();
        E2E::clearRun($run);
        expect(is_dir($directory))->toBeFalse();
        expect(is_dir($run))->toBeFalse();
    } finally {
        DisposablePostgreSql::clearEnvironmentDirectory($directory);
        if (is_dir($run)) {
            E2E::clearRun($run);
        }
    }
});

test('E2E refuses to clean an unverified bootstrap directory from its private ledger', function () {
    $run = dirname(__DIR__, 2).'/.phpunit.cache/e2e-run-'.bin2hex(random_bytes(12));
    $ledger = $run.'/bootstrap-directories.jsonl';
    try {
        expect(mkdir($run.'/unrelated', 0700, true))->toBeTrue();
        expect(file_put_contents($run.'/unrelated/sentinel', 'Synthetic preserved file'))->not->toBeFalse();
        expect(file_put_contents($ledger, json_encode($run.'/unrelated', JSON_THROW_ON_ERROR)."\n"))->not->toBeFalse();
        expect(fn () => E2E::clearRun($run))->toThrow(RuntimeException::class);
        expect(is_file($run.'/unrelated/sentinel'))->toBeTrue();
    } finally {
        if (is_file($ledger)) {
            unlink($ledger);
        }
        if (is_dir($run)) {
            E2E::clearRun($run);
        }
    }
});
