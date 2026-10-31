<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/** One bounded child, joined to the parent's receipt-verified disposable database. */
final class PostgreSqlWorker
{
    private Process $process;

    private InputStream $input;

    private string $directory;

    /** @param array<string, string> $environment
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $receipt
     */
    public function __construct(DisposablePostgreSql $lease, Application $app, array $environment, string $operation, array $data = [], ?array $receipt = null)
    {
        $root = dirname(__DIR__, 2);
        $verified = $lease->workerReceipt($app);
        $this->directory = $root.'/.phpunit.cache/pg-environment-'.bin2hex(random_bytes(12));
        mkdir($this->directory, 0700);
        $clean = [];
        foreach (array_keys(getenv()) as $key) {
            if (preg_match('/\A(?:APP_ENV|APP_.*_CACHE|INTELLEDGER_PG_INTEGRATION|DB_.*|DATABASE_URL|PG.*)\z/', $key)) {
                $clean[$key] = false;
            }
        }
        $this->input = new InputStream;
        $this->input->write(json_encode(['receipt' => $receipt ?? $verified,
            'directory' => $this->directory, 'operation' => $operation, 'data' => $data], JSON_THROW_ON_ERROR)."\n");
        $this->process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', 'tests/PostgreSqlWorker.php'], $root,
            array_replace($clean, $environment), $this->input);
        $this->process->setTimeout(20);
        try {
            $this->process->start();
        } catch (\Throwable $exception) {
            $this->close();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function event(string $event): array
    {
        $found = null;
        $deadline = hrtime(true) + 12_000_000_000;
        do {
            $this->process->checkTimeout();
            $output = $this->process->getOutput();
            if (strlen($output) + strlen($this->process->getErrorOutput()) > 8192) {
                throw new RuntimeException('PostgreSQL worker output exceeded its bound.');
            }
            foreach (explode("\n", trim($output)) as $line) {
                if ($line === '') {
                    continue;
                }
                $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (($message['event'] ?? null) === $event) {
                    $found = $message;
                    break;
                }
            }
            if ($found !== null) {
                return $found;
            }
            if (! $this->process->isRunning()) {
                break;
            }
            usleep(10_000);
        } while (hrtime(true) < $deadline);
        throw new RuntimeException('PostgreSQL worker did not reach the required barrier.');
    }

    /** @return array<string, mixed> */
    public function result(int $expectedExit = 0): array
    {
        $result = $this->event('result');
        $this->input->close();
        $this->process->wait();
        if ($this->process->getExitCode() !== $expectedExit) {
            throw new RuntimeException('PostgreSQL worker failed; payload withheld.');
        }

        return $result;
    }

    /** Terminate only this Process instance, never PostgreSQL or another worker. */
    public function terminate(): void
    {
        $this->process->stop(0, 9);
        if ($this->process->isRunning()) {
            throw new RuntimeException('Owned PostgreSQL test child did not stop.');
        }
    }

    public function close(): void
    {
        if ($this->process->isRunning()) {
            $this->terminate();
        }
        $this->input->close();
        DisposablePostgreSql::clearEnvironmentDirectory($this->directory);
    }
}
