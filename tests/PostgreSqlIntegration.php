<?php

declare(strict_types=1);

use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\CreateTransfer;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\TransactionRevision;
use App\Models\TransactionSourceReference;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\DisposablePostgreSql;
use Tests\Support\PostgreSqlScenarios;

require dirname(__DIR__).'/vendor/autoload.php';

$app = null;
$lease = null;
$directory = null;
$stage = 'configuration preflight';
$tests = 0;
$assertions = 0;
$exit = 0;
$cleanupVerified = false;
$assert = static function (bool $condition) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        throw new RuntimeException('PostgreSQL integration assertion failed.');
    }
};
$rejected = static function (Closure $operation, string $state) use ($assert): void {
    try {
        DB::transaction($operation);
        throw new RuntimeException('Expected PostgreSQL constraint rejection.');
    } catch (QueryException $exception) {
        $assert(($exception->errorInfo[0] ?? null) === $state);
    }
};
$input = ['amount' => '9999999999999999.12345678', 'kind' => 'income', 'status' => 'posted',
    'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02'];

try {
    $assert(PHP_SAPI === 'cli');
    $environment = DisposablePostgreSql::environment();
    $connection = DisposablePostgreSql::configuration($environment);
    $lease = new DisposablePostgreSql($connection);
    $directory = dirname(__DIR__).'/.phpunit.cache/pg-environment-'.bin2hex(random_bytes(12));
    if (! is_dir(dirname($directory))) {
        mkdir(dirname($directory), 0700, true);
    }
    mkdir($directory, 0700);
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $lease->prepare($app, $directory);
    $stage = 'isolated provisioning and application bootstrap';
    $app->make(Kernel::class)->bootstrap();
    $lease->assertOwned($app);
    DB::statement("SET lock_timeout='5s'");
    DB::statement("SET statement_timeout='30s'");

    $stage = 'fresh migrations';
    $assert(DB::selectOne("SELECT count(*) AS count FROM pg_tables WHERE schemaname='public'")->count === 0);
    $lease->assertOwned($app);
    $paths = glob(dirname(__DIR__).'/database/migrations/*.php');
    sort($paths);
    $assert(count($paths) >= 3 && basename($paths[count($paths) - 3]) === '2026_10_27_031919_create_transaction_import_batches_table.php');
    $assert(Artisan::call('migrate', ['--database' => 'pgsql', '--path' => array_slice($paths, 0, -3), '--realpath' => true,
        '--force' => true, '--no-interaction' => true]) === 0);
    $assert(DB::table('migrations')->count() === count($paths) - 3);
    $tests++;

    $stage = 'populated forward migration from the actual pre-import boundary';
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $source = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $destination = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $source->id, 'amount' => '12345.12345678']);
    app(CreateTransaction::class)->handle($actor, $team, $source, $input);
    app(CreateTransfer::class)->handle($actor, $team, [
        'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
        'source_amount' => '12.00000001', 'destination_amount' => '12.00000001',
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02',
    ]);
    $tables = ['users', 'teams', 'team_members', 'financial_accounts', 'financial_account_owners', 'account_balances',
        'account_transactions', 'transaction_revisions', 'transaction_transfers'];
    $snapshot = static function () use ($tables): array {
        $rows = [];
        foreach ($tables as $table) {
            $rows[$table] = json_encode(DB::table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR);
        }

        return $rows;
    };
    $before = $snapshot();
    $lease->assertOwned($app);
    $assert(Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true, '--no-interaction' => true]) === 0);
    $assert(DB::table('migrations')->count() === count(glob(dirname(__DIR__).'/database/migrations/*.php')));
    $assert($snapshot() === $before);
    $assert(Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true, '--no-interaction' => true]) === 0 && $snapshot() === $before);
    $tests++;

    $check = static function (string $name, Closure $operation) use ($app, $lease, &$stage, &$tests, $assert): void {
        $stage = $name;
        $lease->assertOwned($app);
        DB::beginTransaction();
        try {
            $operation();
            $assert(DB::transactionLevel() === 1);
            $tests++;
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    };

    $check('composite Team foreign keys', static function () use ($assert, $rejected): void {
        $account = FinancialAccount::factory()->create();
        $other = FinancialAccount::factory()->create();
        $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
        $rejected(fn () => DB::table('account_balances')->where('id', $balance->id)->update(['team_id' => $other->team_id]), '23503');
        $assert($balance->fresh()->team_id === $account->team_id);
    });

    $check('exact financial decimals and audit snapshot', static function () use ($assert, $input): void {
        $actor = User::factory()->create();
        $team = $actor->personalTeam();
        $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
        $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '-12345.00000001']);
        $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, $input);
        $assert($transaction->fresh()->amount === $input['amount']);
        $assert($balance->fresh()->amount === '-12345.00000001');
        $assert($transaction->revisions()->count() === 1);
        $assert($transaction->revisions()->firstOrFail()->snapshot['amount'] === $input['amount']);
    });

    $check('nested transaction and audit rollback integrity', static function () use ($assert, $input): void {
        $actor = User::factory()->create();
        $team = $actor->personalTeam();
        $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
        $before = [AccountTransaction::count(), TransactionRevision::count(), AccountBalance::count()];
        try {
            DB::transaction(static function () use ($actor, $team, $account, $input): void {
                app(CreateTransaction::class)->handle($actor, $team, $account, $input);
                throw new RuntimeException('Injected integration rollback');
            });
            throw new LogicException('Rollback injection did not execute.');
        } catch (RuntimeException $exception) {
            $assert($exception->getMessage() === 'Injected integration rollback');
        }
        $assert([AccountTransaction::count(), TransactionRevision::count(), AccountBalance::count()] === $before);
    });

    $check('source identity uniqueness and account foreign keys', static function () use ($assert, $rejected): void {
        $before = TransactionSourceReference::count();
        $transaction = AccountTransaction::factory()->posted()->create();
        $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id]);
        $rejected(fn () => TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id,
            'source_namespace' => $reference->source_namespace, 'source_key_hash' => $reference->source_key_hash]), '23505');
        $other = FinancialAccount::factory()->create(['team_id' => $transaction->team_id]);
        $rejected(fn () => DB::table('transaction_source_references')->where('id', $reference->id)->update(['financial_account_id' => $other->id]), '23503');
        TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id,
            'source_namespace' => 'other-import', 'source_key_hash' => $reference->source_key_hash]);
        $assert(TransactionSourceReference::count() === $before + 2);
    });

    $check('Action tenant isolation and independent balance observations', static function () use ($assert, $input): void {
        $actor = User::factory()->create();
        $team = $actor->personalTeam();
        $other = FinancialAccount::factory()->create();
        $balance = AccountBalance::factory()->create(['financial_account_id' => $other->id]);
        $before = $balance->fresh()->getRawOriginal();
        $counts = [AccountTransaction::count(), TransactionRevision::count()];
        try {
            app(CreateTransaction::class)->handle($actor, $team, $other, $input);
            throw new RuntimeException('Cross-Team account was accepted.');
        } catch (ModelNotFoundException) {
            $assert([AccountTransaction::count(), TransactionRevision::count()] === $counts);
        }
        $assert($balance->fresh()->getRawOriginal() === $before);
    });

    $scenario = static function (string $name, Closure $operation) use ($app, $lease, &$stage, &$tests, $assert): void {
        $stage = $name;
        $lease->assertOwned($app);
        $assert(DB::transactionLevel() === 0);
        $operation();
        $lease->assertOwned($app);
        $assert(DB::transactionLevel() === 0);
        $tests++;
    };
    PostgreSqlScenarios::run($app, $lease, $environment, $scenario, $assert);
} catch (Throwable $exception) {
    fwrite(STDERR, 'PostgreSQL integration failed at '.$stage.' ('.class_basename($exception).'); no exception payload is logged.'.PHP_EOL);
    $exit = 1;
} finally {
    try {
        $lease?->cleanup($app);
        DisposablePostgreSql::clearEnvironmentDirectory($directory);
        $cleanupVerified = true;
    } catch (Throwable) {
        fwrite(STDERR, 'Owned database or private bootstrap cleanup could not be verified; refusing success.'.PHP_EOL);
        $exit = 1;
    }
}

echo json_encode(['tool' => 'postgres-integration', 'result' => $exit === 0 ? 'passed' : 'failed',
    'tests' => $tests, 'assertions' => $assertions, 'cleanup' => $cleanupVerified ? 'verified' : 'failed'], JSON_THROW_ON_ERROR).PHP_EOL;
exit($exit);
