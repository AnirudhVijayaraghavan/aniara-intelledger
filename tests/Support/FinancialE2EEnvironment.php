<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** Test-only adapter around EQ-07's existing database capability, never a second lease. */
final class FinancialE2EEnvironment
{
    public const string OPT_IN = 'disposable-financial-e2e-only';

    public const string URL = 'http://127.0.0.1:4182';

    /** Unwind the POSIX supervisor so its finally block stops only its owned child. */
    public static function handleTerminationSignals(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }
        self::require(function_exists('pcntl_async_signals') && function_exists('pcntl_signal'));
        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT] as $signal) {
            self::require(pcntl_signal($signal, static function (int $signal): never {
                throw new RuntimeException('Owned E2E supervisor interrupted.');
            }));
        }
    }

    /** @param array<string, string> $environment */
    public static function assertInvocation(array $environment, bool $worker = false): void
    {
        self::require(($environment['INTELLEDGER_FINANCIAL_E2E'] ?? '') === self::OPT_IN);
        foreach (['APP_RUNNING_IN_CONSOLE', 'PHP_CLI_SERVER_WORKERS', 'ASSET_URL', 'LARAVEL_STORAGE_PATH', 'VIEW_COMPILED_PATH'] as $name) {
            self::require(($environment[$name] ?? '') === '');
        }
        if (! $worker) {
            self::require(($environment['INTELLEDGER_E2E_CONTEXT'] ?? '') === '');
        }
    }

    /** @return array{receipt: array{database: string, oid: int, nonce: string, parent_pid: int}, run: string, key: string} */
    public static function context(string $json): array
    {
        self::require(strlen($json) <= 4096);
        $context = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::require(is_array($context) && count($context) === 3 && is_array($context['receipt'] ?? null));
        DisposablePostgreSql::validateWorkerReceipt($context['receipt']);
        self::require(is_string($context['run'] ?? null) && is_string($context['key'] ?? null)
            && str_starts_with($context['key'], 'base64:')
            && strlen(base64_decode(substr($context['key'], 7), true) ?: '') === 32);
        self::assertRun($context['run']);

        return $context;
    }

    public static function assertRun(string $directory): void
    {
        self::require(preg_match('/\Ae2e-run-[a-f0-9]{24}\z/', basename($directory)) === 1
            && realpath(dirname($directory)) === realpath(dirname(__DIR__, 2).'/.phpunit.cache')
            && realpath($directory) === realpath(dirname($directory)).DIRECTORY_SEPARATOR.basename($directory)
            && ! is_link($directory));
    }

    /** Reserve-and-release preflight; the HTTP bind and Playwright also refuse reuse. */
    public static function assertPortFree(): void
    {
        $listener = @stream_socket_client('tcp://127.0.0.1:4182', $code, $message, 0.25);
        if (is_resource($listener)) {
            fclose($listener);
            self::require(false);
        }
        $socket = @stream_socket_server('tcp://127.0.0.1:4182', $code, $message);
        self::require(is_resource($socket));
        fclose($socket);
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  array<string, mixed>|null  $receipt
     * @return array{app: Application, lease: DisposablePostgreSql, directory: string}
     */
    public static function boot(array $connection, string $run, string $key, ?array $receipt = null): array
    {
        self::assertRun($run);
        $directory = dirname(__DIR__, 2).'/.phpunit.cache/pg-environment-'.bin2hex(random_bytes(12));
        mkdir($directory, 0700);
        $app = null;
        try {
            self::require(file_put_contents($run.'/bootstrap-directories.jsonl', json_encode($directory, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX) !== false);
            $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
            $app->useStoragePath($run.'/storage');
            $lease = new DisposablePostgreSql($connection);
            $lease->prepare($app, $directory, $receipt);
            $app->beforeBootstrapping(RegisterProviders::class, static function (Application $app) use ($run, $key): void {
                $app->make('config')->set([
                    'app.key' => $key, 'app.url' => self::URL, 'app.asset_url' => null, 'app.debug' => false,
                    'view.compiled' => $run.'/storage/framework/views',
                    'session.driver' => 'file', 'session.files' => $run.'/storage/framework/sessions',
                    'session.cookie' => 'intelledger_'.basename($run), 'session.domain' => null,
                    'session.secure' => false, 'session.same_site' => 'lax',
                    'cache.default' => 'file', 'cache.stores.file.path' => $run.'/storage/framework/cache/data',
                    'cache.stores.file.lock_path' => $run.'/storage/framework/cache/data',
                    'queue.default' => 'sync', 'mail.default' => 'array', 'logging.default' => 'null',
                    'inertia.ssr.enabled' => false,
                    'filesystems.default' => 'local', 'filesystems.disks' => [
                        'local' => ['driver' => 'local', 'root' => $run.'/storage/app/private', 'serve' => false, 'throw' => true],
                        'transaction_imports' => ['driver' => 'local', 'root' => $run.'/storage/imports',
                            'serve' => false, 'visibility' => 'private', 'throw' => true, 'report' => false],
                    ],
                ]);
                $app->afterResolving(Vite::class, static function (Vite $vite) use ($run): void {
                    $vite->useHotFile($run.'/never-hot');
                });
            });
            $app->make(Kernel::class)->bootstrap();
            $lease->assertOwned($app);
            DB::statement("SET lock_timeout='3s'");
            DB::statement("SET statement_timeout='15s'");
            self::require($app->make(Vite::class)->hotFile() === $run.'/never-hot'
                && ! $app->make(Vite::class)->isRunningHot());
            Http::preventStrayRequests();

            return ['app' => $app, 'lease' => $lease, 'directory' => $directory];
        } catch (Throwable $exception) {
            if ($app?->resolved('db')) {
                $app->make('db')->purge('pgsql');
            }
            // A provisioning failure still needs the original EQ-07 cleanup checks.
            if (isset($lease) && $receipt === null) {
                $lease->cleanup($app);
            }
            DisposablePostgreSql::clearEnvironmentDirectory($directory);
            throw $exception;
        }
    }

    /** @param array{app: Application, lease: DisposablePostgreSql, directory: string} $boot */
    public static function closeWorker(array $boot): void
    {
        $boot['app']->make('db')->purge('pgsql');
        DisposablePostgreSql::clearEnvironmentDirectory($boot['directory']);
    }

    /** @param array<string, string> $environment
     * @param  array<string, mixed>  $context
     * @return array<string, string|false>
     */
    public static function childEnvironment(array $environment, array $context): array
    {
        $clean = [];
        foreach (array_keys(getenv()) as $name) {
            if (preg_match('/\A(?:APP_.*|DB_.*|DATABASE_URL|PG.*|SESSION_.*|CACHE_.*|MAIL_.*|QUEUE_.*|REDIS_.*|AWS_.*|FILESYSTEM_.*|VITE_.*|LARAVEL_.*|PHP_CLI_SERVER_WORKERS)\z/', $name)) {
                $clean[$name] = false;
            }
        }

        return array_replace($clean, $environment, [
            'INTELLEDGER_FINANCIAL_E2E' => self::OPT_IN,
            'INTELLEDGER_E2E_CONTEXT' => json_encode($context, JSON_THROW_ON_ERROR),
            'NODE_OPTIONS' => '--max-old-space-size=512 --max-semi-space-size=4',
            'ROLLDOWN_WORKER_THREADS' => '1', 'RAYON_NUM_THREADS' => '1',
        ]);
    }

    /** Delete only the invocation's private tree; never follow symlinks. */
    public static function clearRun(string $run): void
    {
        self::assertRun($run);
        $ledger = $run.'/bootstrap-directories.jsonl';
        if (is_file($ledger)) {
            self::require(! is_link($ledger));
            $directories = file($ledger, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::require(is_array($directories));
            foreach ($directories as $line) {
                $directory = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::require(is_string($directory));
                DisposablePostgreSql::clearEnvironmentDirectory($directory);
            }
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($run, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            self::require(! $file->isLink());
            self::require($file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()));
        }
        self::require(rmdir($run));
    }

    private static function require(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Financial E2E safety check failed; refusing execution.');
        }
    }
}
