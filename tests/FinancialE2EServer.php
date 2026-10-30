<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\DisposablePostgreSql;
use Tests\Support\FinancialE2EEnvironment as E2E;

require dirname(__DIR__).'/vendor/autoload.php';

$server = null;
$exit = 1;
try {
    E2E::assertInvocation(getenv(), worker: true);
    E2E::handleTerminationSignals();
    $environment = DisposablePostgreSql::environment();
    $connection = DisposablePostgreSql::configuration($environment);
    $context = E2E::context(getenv('INTELLEDGER_E2E_CONTEXT') ?: '');
    $boot = E2E::boot($connection, $context['run'], $context['key'], $context['receipt']);
    E2E::closeWorker($boot);
    unset($boot);
    E2E::assertPortFree();
    $server = new Process([PHP_BINARY, '-d', 'memory_limit=128M', '-d', 'expose_php=0',
        '-d', 'upload_tmp_dir='.$context['run'].'/storage/uploads',
        '-S', '127.0.0.1:4182', '-t', 'public', 'tests/Support/financial-e2e-router.php'], dirname(__DIR__),
        E2E::childEnvironment($environment, $context));
    $server->setTimeout(660);
    $server->disableOutput();
    $server->start();
    echo "Verified leased E2E HTTP child started.\n";
    $exit = $server->wait();
} catch (Throwable) {
    fwrite(STDERR, "Financial E2E server refused or failed; no payload logged.\n");
} finally {
    if ($server?->isRunning()) {
        $server->stop(1);
    }
}
exit($exit);
