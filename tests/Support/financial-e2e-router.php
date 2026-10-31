<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\Support\DisposablePostgreSql;
use Tests\Support\FinancialE2EEnvironment as E2E;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$boot = null;
try {
    if (PHP_SAPI !== 'cli-server' || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:4182') {
        throw new RuntimeException('Dedicated loopback HTTP server required.');
    }
    E2E::assertInvocation(getenv(), worker: true);
    $connection = DisposablePostgreSql::configuration(DisposablePostgreSql::environment());
    $context = E2E::context(getenv('INTELLEDGER_E2E_CONTEXT') ?: '');
    $identity = hash('sha256', $context['receipt']['nonce']);
    $build = realpath(dirname(__DIR__, 2).'/public/build');
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $asset = is_string($path) ? realpath(dirname(__DIR__, 2).'/public'.rawurldecode($path)) : false;
    if ($asset !== false && $build !== false && is_file($asset)
        && str_starts_with($asset, $build.DIRECTORY_SEPARATOR)
        && in_array(pathinfo($asset, PATHINFO_EXTENSION), ['js', 'css', 'woff', 'woff2', 'svg', 'png', 'ico'], true)) {
        header('X-IntelLedger-E2E-Lease: '.$identity);

        return false;
    }
    $boot = E2E::boot($connection, $context['run'], $context['key'], $context['receipt']);
    $boot['lease']->assertOwned($boot['app']);
    $kernel = $boot['app']->make(Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->headers->set('X-IntelLedger-E2E-Lease', $identity);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Isolated E2E request refused.';
} finally {
    if ($boot !== null) {
        E2E::closeWorker($boot);
    }
}
