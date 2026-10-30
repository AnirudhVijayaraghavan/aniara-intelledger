<?php

declare(strict_types=1);

use App\Actions\Finance\ReverseTransaction;
use App\Actions\Finance\ReverseTransfer;
use App\Models\Team;
use App\Models\TransactionSourceReference;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\DisposablePostgreSql;

require dirname(__DIR__).'/vendor/autoload.php';

$emit = static function (array $message): void {
    echo json_encode($message, JSON_THROW_ON_ERROR)."\n";
    flush();
};
$lease = null;
$app = null;
$directory = null;
$ownsDatabase = false;
$exit = 0;
try {
    $connection = DisposablePostgreSql::configuration(DisposablePostgreSql::environment());
    $payload = fgets(STDIN, 16385);
    if ($payload === false || ! str_ends_with($payload, "\n")) {
        throw new RuntimeException('Bounded worker capability required.');
    }
    $payload = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    $directory = $payload['directory'];
    if (realpath(dirname($directory)) !== realpath(dirname(__DIR__).'/.phpunit.cache')
        || preg_match('/\Apg-environment-[a-f0-9]{24}\z/', basename($directory)) !== 1) {
        throw new RuntimeException('Private worker directory required.');
    }
    $lease = new DisposablePostgreSql($connection);
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $ownsDatabase = $payload['operation'] === 'provision-refusal';
    $lease->prepare($app, $directory, $ownsDatabase ? null : $payload['receipt']);
    $app->make(Kernel::class)->bootstrap();
    $lease->assertOwned($app);
    DB::statement("SET lock_timeout='5s'");
    DB::statement("SET statement_timeout='8s'");
    $pid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
    $emit(['event' => 'ready', 'pid' => $pid]);
    $data = $payload['data'];
    $operation = $payload['operation'];
    if ($operation === 'cleanup-refusal') {
        try {
            $lease->cleanup($app);
            throw new LogicException('Worker cannot dispose its parent lease.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'PostgreSQL integration safety check failed; refusing database mutation.') {
                throw $exception;
            }
            $emit(['event' => 'result', 'outcome' => 'refused']);
        }
    } else {
        $lease->assertOwned($app);
        if ($operation === 'hold-transfer') {
            DB::beginTransaction();
        }
        if (in_array($operation, ['reverse-transaction', 'reverse-transfer', 'hold-transfer'], true)) {
            $actor = User::findOrFail($data['actor']);
            $team = Team::findOrFail($data['team']);
            if ($operation === 'reverse-transaction') {
                app(ReverseTransaction::class)->handle($actor, $team, $team->accountTransactions()->findOrFail($data['id']), $data['input']);
            } else {
                app(ReverseTransfer::class)->handle($actor, $team, $team->transactionTransfers()->findOrFail($data['id']), $data['input']);
            }
        } elseif ($operation === 'source-identity') {
            TransactionSourceReference::create($data);
        } else {
            throw new RuntimeException('Unknown worker operation.');
        }
        if ($operation === 'hold-transfer') {
            $emit(['event' => 'staged', 'pid' => $pid]);
            $lease->waitForParent();
        }
        $emit(['event' => 'result', 'outcome' => 'accepted']);
    }
} catch (ValidationException $exception) {
    $emit(['event' => 'result', 'outcome' => 'validation', 'fields' => array_keys($exception->errors())]);
} catch (QueryException $exception) {
    $emit(['event' => 'result', 'outcome' => 'constraint', 'sqlstate' => $exception->errorInfo[0] ?? null]);
} catch (Throwable $exception) {
    $safety = $exception instanceof RuntimeException && $exception->getMessage() === 'PostgreSQL integration safety check failed; refusing database mutation.';
    $emit(['event' => 'result', 'outcome' => $safety ? 'refused' : 'failed']);
    $exit = 1;
} finally {
    try {
        if ($ownsDatabase) {
            $lease?->cleanup($app);
        } elseif ($app !== null && $app->resolved('db')) {
            $app->make('db')->purge('pgsql');
        }
        DisposablePostgreSql::clearEnvironmentDirectory($directory);
    } catch (Throwable) {
        $exit = 1;
    }
}
exit($exit);
