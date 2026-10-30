<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\Support\DisposablePostgreSql;
use Tests\Support\FinancialE2EEnvironment as E2E;

require dirname(__DIR__).'/vendor/autoload.php';

$boot = null;
$run = null;
$process = null;
$exit = 1;
$stage = 'configuration preflight';
$cleanup = false;
try {
    E2E::assertInvocation(getenv());
    $environment = DisposablePostgreSql::environment();
    $connection = DisposablePostgreSql::configuration($environment);
    if (PHP_SAPI !== 'cli' || ! in_array(array_slice($argv, 1), [[], ['--probe-server']], true)
        || ! is_file(dirname(__DIR__).'/public/build/manifest.json')) {
        throw new RuntimeException('Built assets and the explicit runner are required.');
    }
    E2E::assertPortFree();
    $run = dirname(__DIR__).'/.phpunit.cache/e2e-run-'.bin2hex(random_bytes(12));
    mkdir($run, 0700);
    foreach (['framework/views', 'framework/sessions', 'framework/cache/data', 'app/private', 'imports', 'uploads', 'logs'] as $path) {
        mkdir($run.'/storage/'.$path, 0700, true);
    }
    $stage = 'lease verification';
    $key = 'base64:'.base64_encode(random_bytes(32));
    $boot = E2E::boot($connection, $run, $key);
    $boot['lease']->assertOwned($boot['app']);
    $stage = 'fresh leased migrations';
    if (Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Owned migration failed.');
    }
    $context = ['receipt' => $boot['lease']->workerReceipt($boot['app']), 'run' => $run, 'key' => $key];
    $child = E2E::childEnvironment($environment, $context);
    $stage = 'guarded browser journeys';
    if (in_array('--probe-server', $argv, true)) {
        foreach (['nonce', 'parent', 'credential'] as $failure) {
            $stage = 'invalid worker '.$failure.' refusal';
            $invalid = $context;
            $invalidEnvironment = $environment;
            if ($failure === 'nonce') {
                $nonce = $context['receipt']['nonce'];
                $invalid['receipt']['nonce'] = ($nonce[0] === '0' ? '1' : '0').substr($nonce, 1);
            } elseif ($failure === 'parent') {
                $invalid['receipt']['parent_pid']++;
            } else {
                $invalidEnvironment['PG_TEST_PASSWORD'] = 'synthetic-invalid-credential';
            }
            $refused = new Process([PHP_BINARY, 'tests/FinancialE2EServer.php'], dirname(__DIR__), E2E::childEnvironment($invalidEnvironment, $invalid));
            $refused->setTimeout(15);
            $refused->run();
            if ($refused->getExitCode() !== 1) {
                throw new RuntimeException('Invalid server capability accepted.');
            }
            E2E::assertPortFree();
        }
        $stage = 'controlled HTTP child interruption';
        $process = new Process([PHP_BINARY, 'tests/FinancialE2EServer.php'], dirname(__DIR__), $child);
        $process->setTimeout(25);
        $process->start();
        $ready = false;
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            $process->checkTimeout();
            $http = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
            @file_get_contents(E2E::URL.'/login', false, $http);
            $ready = in_array('X-IntelLedger-E2E-Lease: '.hash('sha256', $context['receipt']['nonce']), $http_response_header ?? [], true);
            if (! $process->isRunning()) {
                break;
            }
            if (! $ready) {
                usleep(10_000);
            }
        } while (! $ready && hrtime(true) < $deadline);
        if (! $ready) {
            throw new RuntimeException('Verified HTTP readiness was not reached.');
        }
        $process->stop(2);
        E2E::assertPortFree();
        $exit = 0;
    } else {
        $process = new Process(['node', 'node_modules/@playwright/test/cli.js', 'test', '--config=playwright.e2e.config.ts'], dirname(__DIR__), $child);
        $process->setTimeout(720);
        $process->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });
        $exit = $process->getExitCode() ?? 1;
    }
} catch (Throwable) {
    fwrite(STDERR, 'Financial E2E refused or failed at '.$stage.'; no exception payload logged.'.PHP_EOL);
} finally {
    try {
        if ($process?->isRunning()) {
            $process->stop(2);
        }
        if ($boot !== null) {
            $boot['lease']->cleanup($boot['app']);
            DisposablePostgreSql::clearEnvironmentDirectory($boot['directory']);
        }
        if ($run !== null) {
            E2E::clearRun($run);
        }
        E2E::assertPortFree();
        $cleanup = true;
    } catch (Throwable) {
        fwrite(STDERR, "E2E cleanup unverified; refusing success. No unrelated server/database is adopted or terminated.\n");
        $exit = 1;
    }
}
echo json_encode(['tool' => 'financial-e2e', 'result' => $exit === 0 ? 'passed' : 'failed',
    'cleanup' => $cleanup ? 'verified' : 'unverified'], JSON_THROW_ON_ERROR).PHP_EOL;
exit($exit);
