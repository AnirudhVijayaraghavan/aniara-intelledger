<?php

/**
 * Run explicitly: php tests/PostgreSqlTransferReversalProbe.php
 * Uses only a newly provisioned disposable PostgreSQL database, never RefreshDatabase.
 * The normal Pest suite and its SQLite-only safety guard remain unchanged.
 */

use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\ReverseTransfer;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\QueryException;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

function pgTransferAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $config */
function pgTransferConnect(array $config, string $database, int $oid, string $nonce): void
{
    pgTransferAssert(preg_match('/\Aintelledger_pg_validation_[a-f0-9]{12}_fresh\z/', $database) === 1
        && $database !== $config['database'] && preg_match('/\A[a-f0-9]{32}\z/', $nonce) === 1, 'Invalid disposable database receipt.');
    unset($config['url'], $config['read'], $config['write'], $config['direct']);
    $config['database'] = $database;
    DB::purge();
    config(['database.default' => 'pg_validation', 'database.connections' => ['pg_validation' => $config],
        'cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
    DB::setDefaultConnection('pg_validation');
    $identity = DB::selectOne('SELECT current_database() AS name, oid FROM pg_database WHERE datname=current_database()');
    pgTransferAssert(DB::getDriverName() === 'pgsql' && $identity->name === $database && (int) $identity->oid === $oid, 'Disposable database identity mismatch.');
    pgTransferAssert(DB::table('__intelledger_probe_context')->value('token') === $nonce, 'Disposable database ownership receipt mismatch.');
    DB::statement("SET lock_timeout='10s'");
    DB::statement("SET statement_timeout='30s'");
}

/** @return array<string, array<string, mixed>> */
function pgTransferSnapshot(PDO $pdo): array
{
    $result = [];
    foreach ($pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $result[$table] = $pdo->query('SELECT COUNT(*) AS count, md5(COALESCE(string_agg(to_jsonb(t)::text, chr(10) ORDER BY to_jsonb(t)::text), \'\')) AS digest FROM public.'.$quoted.' t')->fetch(PDO::FETCH_ASSOC);
    }

    return $result;
}

/** @return list<int> */
function pgTransferCounts(): array
{
    return [TransactionTransfer::count(), AccountTransaction::count(), TransactionRevision::count()];
}

/** @return array<string, mixed> */
function pgTransferReversalInput(): array
{
    return ['expected_source_revision' => 1, 'expected_destination_revision' => 1,
        'posted_date' => '2026-10-03', 'reason' => 'PostgreSQL transfer reversal validation'];
}

/** @param Closure(): mixed $operation */
function pgTransferSqlRejection(Closure $operation, string $state, ?string $constraint = null): void
{
    try {
        DB::transaction($operation);
        throw new RuntimeException('Expected database constraint rejection.');
    } catch (QueryException $exception) {
        pgTransferAssert(($exception->errorInfo[0] ?? null) === $state, 'Unexpected PostgreSQL constraint result.');
        if ($constraint !== null) {
            pgTransferAssert(str_contains($exception->errorInfo[2] ?? '', $constraint), 'Expected constraint was not enforced.');
        }
    }
}

$stage = 'bootstrap';
$admin = null;
$ownedDatabase = null;
$processes = [];
$exitCode = 0;
$checks = 0;

try {
    pgTransferAssert(PHP_SAPI === 'cli', 'This integration probe is CLI-only.');
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
    pgTransferAssert($app->environment('local') && ($config['driver'] ?? null) === 'pgsql'
        && ! isset($config['read']) && ! isset($config['write']) && ! isset($config['direct']), 'Local single-endpoint PostgreSQL configuration is required.');

    if (($argv[1] ?? null) === 'worker') {
        $context = json_decode($argv[2] ?? '', true, flags: JSON_THROW_ON_ERROR);
        pgTransferConnect($config, $context['database'], $context['oid'], $context['nonce']);
        DB::selectOne("SELECT set_config('application_name', ?, false)", [$context['application']]);
        $actor = User::findOrFail($context['actor']);
        $team = Team::findOrFail($context['team']);
        $transfer = $team->transactionTransfers()->findOrFail($context['transfer']);
        try {
            app(ReverseTransfer::class)->handle($actor, $team, $transfer, pgTransferReversalInput());
            echo json_encode(['outcome' => 'created']).PHP_EOL;
        } catch (ValidationException $exception) {
            pgTransferAssert(isset($exception->errors()['transfer']), 'Unexpected worker validation failure.');
            echo json_encode(['outcome' => 'conflict']).PHP_EOL;
        }
    } else {
        $stage = 'disposable database provisioning';
        $admin = DB::getPdo();
        $before = pgTransferSnapshot($admin);
        $database = 'intelledger_pg_validation_'.bin2hex(random_bytes(6)).'_fresh';
        $nonce = bin2hex(random_bytes(16));
        $statement = $admin->prepare('SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname=?)');
        $statement->execute([$database]);
        pgTransferAssert(! $statement->fetchColumn() && $database !== $config['database'], 'Disposable database must not already exist.');
        $admin->exec('CREATE DATABASE "'.$database.'" TEMPLATE template0');
        $ownedDatabase = $database;
        $statement = $admin->prepare('SELECT oid FROM pg_database WHERE datname=?');
        $statement->execute([$database]);
        $oid = (int) $statement->fetchColumn();
        $targetConfig = array_replace($config, ['database' => $database]);
        unset($targetConfig['url']);
        $target = (new PostgresConnector)->connect($targetConfig);
        pgTransferAssert($target->query('SELECT current_database()')->fetchColumn() === $database, 'Provisioned target identity mismatch.');
        $target->exec('CREATE TABLE __intelledger_probe_context (token TEXT PRIMARY KEY)');
        $target->prepare('INSERT INTO __intelledger_probe_context(token) VALUES (?)')->execute([$nonce]);
        $target = null;
        pgTransferConnect($config, $database, $oid, $nonce);

        $stage = 'populated PostgreSQL migration';
        $migration = 'database/migrations/2026_10_26_222631_add_reversal_link_to_transaction_transfers_table.php';
        $prior = array_values(array_filter(glob(dirname(__DIR__).'/database/migrations/*.php'), fn (string $path): bool => basename($path) !== basename($migration)));
        pgTransferAssert(Artisan::call('migrate', ['--database' => 'pg_validation', '--path' => $prior, '--realpath' => true, '--force' => true, '--no-interaction' => true]) === 0, 'Baseline migrations failed.');
        $actor = User::factory()->create();
        $team = $actor->personalTeam();
        $source = FinancialAccount::factory()->create(['team_id' => $team->id]);
        $destination = FinancialAccount::factory()->create(['team_id' => $team->id]);
        $common = ['kind' => 'transfer', 'status' => 'posted', 'excluded_from_analytics' => true, 'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02'];
        $sourceLeg = app(CreateTransaction::class)->handle($actor, $team, $source, [...$common, 'amount' => '-500']);
        $destinationLeg = app(CreateTransaction::class)->handle($actor, $team, $destination, [...$common, 'amount' => '500']);
        $originalId = DB::table('transaction_transfers')->insertGetId([
            'team_id' => $team->id, 'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
            'source_transaction_id' => $sourceLeg->id, 'destination_transaction_id' => $destinationLeg->id,
            'recorded_at' => now()->utc()->format('Y-m-d H:i:s.u'), 'created_by' => $actor->id,
        ]);
        AccountBalance::factory()->create(['financial_account_id' => $source->id]);
        $balances = AccountBalance::orderBy('id')->get()->toArray();
        $originalBefore = (array) DB::table('transaction_transfers')->find($originalId);
        pgTransferAssert(Artisan::call('migrate', ['--database' => 'pg_validation', '--path' => $migration, '--force' => true, '--no-interaction' => true]) === 0, 'Additive reversal migration failed.');
        $migrated = (array) DB::table('transaction_transfers')->find($originalId);
        pgTransferAssert($migrated['reverses_transfer_id'] === null, 'Existing group acquired a reversal link.');
        unset($migrated['reverses_transfer_id']);
        pgTransferAssert($migrated === $originalBefore, 'Migration altered existing group data.');
        $checks++;

        $stage = 'simultaneous reversal requests';
        DB::beginTransaction();
        Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
        foreach (['a', 'b'] as $label) {
            $context = ['database' => $database, 'oid' => $oid, 'nonce' => $nonce, 'actor' => $actor->id,
                'team' => $team->id, 'transfer' => $originalId, 'application' => $database.'_'.$label];
            $process = new Process([PHP_BINARY, __FILE__, 'worker', json_encode($context, JSON_THROW_ON_ERROR)], dirname(__DIR__));
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 8;
        $blocked = 0;
        $statement = $admin->prepare("SELECT COUNT(*) FROM pg_stat_activity WHERE datname=? AND application_name IN (?, ?) AND wait_event_type='Lock'");
        do {
            $statement->execute([$database, $database.'_a', $database.'_b']);
            $blocked = (int) $statement->fetchColumn();
            if ($blocked < 2) {
                usleep(20000);
            }
        } while ($blocked < 2 && microtime(true) < $deadline);
        DB::commit();
        pgTransferAssert($blocked === 2, 'Both Action workers did not overlap at the team lock.');
        $outcomes = [];
        foreach ($processes as $process) {
            $process->wait();
            pgTransferAssert($process->isSuccessful(), 'Reversal worker failed.');
            $outcomes[] = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR)['outcome'];
        }
        sort($outcomes);
        pgTransferAssert($outcomes === ['conflict', 'created'] && pgTransferCounts() === [2, 4, 4], 'Concurrent requests created duplicate or partial reversals.');
        $reversal = TransactionTransfer::where('reverses_transfer_id', $originalId)->sole();
        foreach ([$reversal->sourceTransaction, $reversal->destinationTransaction] as $leg) {
            pgTransferAssert($leg->revisions()->sole()->snapshot === $leg->fresh()->canonicalSnapshot(), 'Concurrent reversal audit mismatch.');
        }
        $checks++;

        $stage = 'database uniqueness and tenant constraints';
        DB::beginTransaction();
        try {
            $candidateSource = app(CreateTransaction::class)->handle($actor, $team, $destination, [...$common, 'amount' => '-500']);
            $candidateDestination = app(CreateTransaction::class)->handle($actor, $team, $source, [...$common, 'amount' => '500']);
            $candidate = $reversal->getAttributes();
            unset($candidate['id']);
            $candidate['source_transaction_id'] = $candidateSource->id;
            $candidate['destination_transaction_id'] = $candidateDestination->id;
            pgTransferSqlRejection(fn () => DB::table('transaction_transfers')->insert($candidate), '23505', 'tt_reversal_unique');
            $foreign = TransactionTransfer::factory()->create();
            pgTransferSqlRejection(fn () => DB::table('transaction_transfers')->where('id', $reversal->id)->update(['reverses_transfer_id' => $foreign->id]), '23503', 'tt_tenant_reversal_fk');
            pgTransferSqlRejection(fn () => DB::table('transaction_transfers')->where('id', $reversal->id)->update(['reverses_transfer_id' => $reversal->id]), '23514', 'tt_reversal_check');
        } finally {
            DB::rollBack();
        }
        $checks++;

        $stage = 'FX exactness stale revisions and rollback';
        $inr = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
        $original = app(CreateTransfer::class)->handle($actor, $team, [
            'source_account_id' => $source->id, 'destination_account_id' => $inr->id,
            'source_amount' => '9999999999999999.12345678', 'destination_amount' => '1234567890123456.00000001',
            'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02',
            'execution_fx' => ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83.123456789012345678', 'source' => 'Original receipt', 'effective_at' => '2026-10-01T12:00:00.123456+05:30'],
        ]);
        ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '99.999999999999999999']);
        $counts = pgTransferCounts();
        try {
            app(ReverseTransfer::class)->handle($actor, $team, $original, [...pgTransferReversalInput(), 'expected_destination_revision' => 2]);
            throw new RuntimeException('Stale destination revision was accepted.');
        } catch (ValidationException $exception) {
            pgTransferAssert(isset($exception->errors()['expected_destination_revision']), 'Incorrect stale revision feedback.');
        }
        $event = 'eloquent.creating: '.TransactionRevision::class;
        $calls = 0;
        Event::listen($event, function () use (&$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('Injected second-audit failure');
            }
        });
        try {
            try {
                app(ReverseTransfer::class)->handle($actor, $team, $original, pgTransferReversalInput());
                throw new RuntimeException('Audit failure was ignored.');
            } catch (RuntimeException $exception) {
                pgTransferAssert($exception->getMessage() === 'Injected second-audit failure', 'Unexpected audit failure.');
            }
        } finally {
            Event::forget($event);
        }
        pgTransferAssert(pgTransferCounts() === $counts && $original->fresh()->reversal === null, 'Failed reversal left partial history.');
        $fxReversal = app(ReverseTransfer::class)->handle($actor, $team, $original, pgTransferReversalInput());
        pgTransferAssert($fxReversal->sourceTransaction->amount === '-1234567890123456.00000001'
            && $fxReversal->destinationTransaction->amount === '9999999999999999.12345678'
            && $fxReversal->fx_rate === null && $fxReversal->reversesTransfer->fx_rate === '83.123456789012345678', 'Booked amounts or historical FX were changed.');
        foreach ([$fxReversal->sourceTransaction, $fxReversal->destinationTransaction] as $leg) {
            $revision = $leg->revisions()->sole();
            pgTransferAssert($revision->snapshot === $leg->fresh()->canonicalSnapshot() && $revision->actor_id === $actor->id
                && $revision->reason === pgTransferReversalInput()['reason'], 'Reversal audit provenance mismatch.');
        }
        $checks++;
        pgTransferAssert(AccountBalance::orderBy('id')->get()->toArray() === $balances, 'Account balance observations changed.');
        pgTransferAssert(pgTransferSnapshot($admin) === $before, 'Development data or migration history changed.');
        $checks++;
        echo json_encode(['result' => 'passed', 'checks' => $checks, 'overlappingWorkers' => 2, 'developmentUnchanged' => true]).PHP_EOL;
    }
} catch (Throwable $exception) {
    $exitCode = 1;
    $detail = $exception instanceof QueryException || $exception instanceof PDOException
        ? ['sqlstate' => $exception->errorInfo[0] ?? $exception->getCode()]
        : ['message' => $exception->getMessage()];
    echo json_encode(['result' => 'failed', 'stage' => $stage, 'exception' => get_class($exception), ...$detail]).PHP_EOL;
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    if ($ownedDatabase !== null && $admin !== null) {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('pg_validation');
            pgTransferAssert(preg_match('/\Aintelledger_pg_validation_[a-f0-9]{12}_fresh\z/', $ownedDatabase) === 1, 'Refusing unexpected database cleanup.');
            $admin->exec('DROP DATABASE "'.$ownedDatabase.'"');
            echo json_encode(['disposableDatabaseRemoved' => true]).PHP_EOL;
        } catch (Throwable) {
            $exitCode = 1;
            echo json_encode(['cleanupFailed' => true, 'disposableDatabase' => $ownedDatabase]).PHP_EOL;
        }
    }
}

exit($exitCode);
