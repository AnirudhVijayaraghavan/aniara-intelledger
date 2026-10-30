<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\ReverseTransaction;
use App\Actions\Finance\ReverseTransfer;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\TransactionSourceReference;
use App\Models\TransactionTransfer;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Real-engine scenarios; committed synthetic fixtures live only in the owned run database. */
final class PostgreSqlScenarios
{
    /** @param array<string, string> $environment */
    public static function run(Application $app, DisposablePostgreSql $lease, array $environment, Closure $check, Closure $assert): void
    {
        $input = ['expected_revision' => 1, 'posted_date' => '2026-10-03', 'reason' => 'Synthetic reversal'];
        $transferInput = ['expected_source_revision' => 1, 'expected_destination_revision' => 1,
            'posted_date' => '2026-10-03', 'reason' => 'Synthetic transfer reversal'];

        $check('concurrent standalone reversal and duplicate retry', static function () use ($app, $lease, $environment, $assert, $input): void {
            [$actor, $team, $account, $original] = self::transaction();
            $snapshot = $original->fresh()->getRawOriginal();
            $worker = null;
            DB::beginTransaction();
            try {
                $reversal = app(ReverseTransaction::class)->handle($actor, $team, $original, $input);
                $worker = new PostgreSqlWorker($lease, $app, $environment, 'reverse-transaction',
                    ['actor' => $actor->id, 'team' => $team->id, 'id' => $original->id, 'input' => $input]);
                $pid = $worker->event('ready')['pid'];
                self::blockedByParent($pid);
                DB::commit();
                $assert($worker->result() === ['event' => 'result', 'outcome' => 'validation', 'fields' => ['reversal']]);
                $assert($reversal->fresh()->amount === '-123.12345678');
                $assert($team->accountTransactions()->count() === 2 && $team->transactionRevisions()->count() === 2);
                $assert($original->fresh()->getRawOriginal() === $snapshot);
                self::audit($reversal, $assert);
            } finally {
                self::rollback();
                $worker?->close();
            }
            $retry = new PostgreSqlWorker($lease, $app, $environment, 'reverse-transaction',
                ['actor' => $actor->id, 'team' => $team->id, 'id' => $original->id, 'input' => $input]);
            try {
                $assert($retry->result()['fields'] === ['reversal']);
                $assert($team->accountTransactions()->count() === 2 && $team->transactionRevisions()->count() === 2);
            } finally {
                $retry->close();
            }
        });

        foreach (['asset', 'liability', 'cross-currency'] as $type) {
            $check('concurrent '.$type.' transfer reversal', static function () use ($app, $lease, $environment, $assert, $transferInput, $type): void {
                [$actor, $team, $original] = self::transfer($type);
                $snapshots = [$original->fresh()->getRawOriginal(), $original->sourceTransaction->fresh()->getRawOriginal(),
                    $original->destinationTransaction->fresh()->getRawOriginal()];
                $balance = AccountBalance::factory()->create(['financial_account_id' => $original->source_account_id, 'amount' => '777.00000001']);
                $balanceBefore = $balance->fresh()->getRawOriginal();
                $worker = null;
                DB::beginTransaction();
                try {
                    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, $transferInput);
                    $worker = new PostgreSqlWorker($lease, $app, $environment, 'reverse-transfer',
                        ['actor' => $actor->id, 'team' => $team->id, 'id' => $original->id, 'input' => $transferInput]);
                    self::blockedByParent($worker->event('ready')['pid']);
                    DB::commit();
                    $assert($worker->result() === ['event' => 'result', 'outcome' => 'validation', 'fields' => ['transfer']]);
                    $assert($team->transactionTransfers()->count() === 2 && $team->accountTransactions()->count() === 4
                        && $team->transactionRevisions()->count() === 4);
                    $assert($reversal->sourceTransaction->amount === match ($type) {
                        'liability' => '500.00000001', 'cross-currency' => '-42000.00000008', default => '-500.00000001',
                    });
                    $assert($reversal->destinationTransaction->amount === '500.00000001');
                    $assert($reversal->sourceTransaction->currency_code === ($type === 'cross-currency' ? 'INR' : 'USD'));
                    $assert($reversal->fx_rate === null && $reversal->fx_source === null && $reversal->reverses_transfer_id === $original->id);
                    $assert([$original->fresh()->getRawOriginal(), $original->sourceTransaction->fresh()->getRawOriginal(),
                        $original->destinationTransaction->fresh()->getRawOriginal()] === $snapshots);
                    $assert($balance->fresh()->getRawOriginal() === $balanceBefore);
                    self::audit($reversal->sourceTransaction, $assert);
                    self::audit($reversal->destinationTransaction, $assert);
                } finally {
                    self::rollback();
                    $worker?->close();
                }
            });
        }

        $check('stale revision rejected after real Team lock contention', static function () use ($app, $lease, $environment, $assert, $input): void {
            [$actor, $team, $account, $original] = self::transaction();
            $worker = null;
            DB::beginTransaction();
            try {
                app(ClassifyTransaction::class)->handle($actor, $team, $original, ['expected_revision' => 1, 'description' => 'Accepted classification']);
                $worker = new PostgreSqlWorker($lease, $app, $environment, 'reverse-transaction',
                    ['actor' => $actor->id, 'team' => $team->id, 'id' => $original->id, 'input' => $input]);
                self::blockedByParent($worker->event('ready')['pid']);
                DB::commit();
                $assert($worker->result() === ['event' => 'result', 'outcome' => 'validation', 'fields' => ['expected_revision']]);
                $assert($team->accountTransactions()->count() === 1 && $original->revisions()->pluck('revision_number')->sort()->values()->all() === [1, 2]);
            } finally {
                self::rollback();
                $worker?->close();
            }
            $reversal = app(ReverseTransaction::class)->handle($actor, $team, $original->fresh(), [...$input, 'expected_revision' => 2]);
            self::audit($reversal, $assert);
        });

        foreach ([true, false] as $commit) {
            $check('source identity contender after '.($commit ? 'commit' : 'rollback'), static function () use ($app, $lease, $environment, $assert, $commit): void {
                [$actor, $team, $account, $transaction] = self::transaction();
                $data = ['team_id' => $team->id, 'financial_account_id' => $account->id, 'account_transaction_id' => $transaction->id,
                    'source_namespace' => 'csv', 'source_key_hash' => hash('sha256', 'synthetic-source'), 'recorded_by' => $actor->id];
                $worker = null;
                DB::beginTransaction();
                try {
                    TransactionSourceReference::create($data);
                    $worker = new PostgreSqlWorker($lease, $app, $environment, 'source-identity', $data);
                    self::blockedByParent($worker->event('ready')['pid']);
                    $commit ? DB::commit() : DB::rollBack();
                    $assert($worker->result() === ($commit
                        ? ['event' => 'result', 'outcome' => 'constraint', 'sqlstate' => '23505']
                        : ['event' => 'result', 'outcome' => 'accepted']));
                    $assert($team->transactionSourceReferences()->count() === 1);
                    $assert($team->accountTransactions()->count() === 1 && $team->transactionRevisions()->count() === 1);
                } finally {
                    self::rollback();
                    $worker?->close();
                }
            });
        }

        $check('second reversal leg audit failure rolls back all accepted work', static function () use ($assert, $transferInput): void {
            [$actor, $team, $original] = self::transfer('cross-currency');
            $before = self::history($team->id);
            $dispatcher = TransactionRevision::getEventDispatcher();
            $temporary = clone $dispatcher;
            $creations = 0;
            $temporary->listen('eloquent.creating: '.TransactionRevision::class, static function () use (&$creations): void {
                if (++$creations === 2) {
                    throw new RuntimeException('Synthetic second-leg audit failure');
                }
            });
            TransactionRevision::setEventDispatcher($temporary);
            try {
                try {
                    app(ReverseTransfer::class)->handle($actor, $team, $original, $transferInput);
                    throw new RuntimeException('Injection was not reached.');
                } catch (RuntimeException $exception) {
                    $assert($exception->getMessage() === 'Synthetic second-leg audit failure');
                }
            } finally {
                TransactionRevision::setEventDispatcher($dispatcher);
            }
            $assert($creations === 2 && self::history($team->id) === $before && DB::transactionLevel() === 0);
            $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, $transferInput);
            self::audit($reversal->sourceTransaction, $assert);
            self::audit($reversal->destinationTransaction, $assert);
        });

        $check('worker termination rolls back transfer legs and forbids active-session disposal', static function () use ($app, $lease, $environment, $assert, $transferInput): void {
            [$actor, $team, $original] = self::transfer('cross-currency');
            $before = self::history($team->id);
            $worker = new PostgreSqlWorker($lease, $app, $environment, 'hold-transfer',
                ['actor' => $actor->id, 'team' => $team->id, 'id' => $original->id, 'input' => $transferInput]);
            try {
                $pid = $worker->event('staged')['pid'];
                $assert(self::history($team->id) === $before);
                try {
                    $lease->cleanup($app);
                    throw new \LogicException('Active worker database was dropped.');
                } catch (RuntimeException $exception) {
                    $assert($exception->getMessage() === 'PostgreSQL integration safety check failed; refusing database mutation.');
                }
                $worker->terminate();
                self::await(static fn (): bool => DB::selectOne('SELECT count(*) AS count FROM pg_stat_activity WHERE pid=?', [$pid])->count === 0);
                $lease->assertOwned($app);
                $assert(self::history($team->id) === $before);
                $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, $transferInput);
                $assert($team->transactionTransfers()->count() === 2 && $team->accountTransactions()->count() === 4);
                self::audit($reversal->sourceTransaction, $assert);
                self::audit($reversal->destinationTransaction, $assert);
            } finally {
                $worker->close();
            }
        });

        $check('outstanding database and forged capabilities are never adopted', static function () use ($app, $lease, $environment, $assert): void {
            $receipt = $lease->workerReceipt($app);
            foreach (['provision-refusal' => $receipt, 'wrong-oid' => [...$receipt, 'oid' => $receipt['oid'] + 1],
                'wrong-nonce' => [...$receipt, 'nonce' => str_repeat('0', 32)],
                'unrelated-database' => [...$receipt, 'database' => 'postgres'],
                'absent-parent' => [...$receipt, 'parent_pid' => 2147483647]] as $probe => $candidate) {
                $worker = new PostgreSqlWorker($lease, $app, $environment, $probe === 'provision-refusal' ? $probe : 'cleanup-refusal', receipt: $candidate);
                try {
                    $assert($worker->result(1) === ['event' => 'result', 'outcome' => 'refused']);
                    $lease->assertOwned($app);
                } finally {
                    $worker->close();
                }
            }
            $worker = new PostgreSqlWorker($lease, $app, $environment, 'cleanup-refusal');
            try {
                $assert($worker->result()['outcome'] === 'refused');
            } finally {
                $worker->close();
            }
            $assert(DB::selectOne("SELECT count(*) AS count FROM pg_database WHERE datname ~ '^intelledger_ci_[a-f0-9]{24}$'")->count === 1);
        });

        $check('nonce tampering refuses parent cleanup without deleting data', static function () use ($app, $lease, $assert): void {
            $receipt = $lease->workerReceipt($app);
            $pdo = DB::connection()->getPdo();
            $pdo->exec('COMMENT ON DATABASE "'.$receipt['database'].'" IS '.$pdo->quote(str_repeat('0', 32)));
            try {
                try {
                    $lease->cleanup($app);
                    throw new \LogicException('Invalid receipt was accepted for cleanup.');
                } catch (RuntimeException $exception) {
                    $assert($exception->getMessage() === 'PostgreSQL integration safety check failed; refusing database mutation.');
                }
            } finally {
                $pdo->exec('COMMENT ON DATABASE "'.$receipt['database'].'" IS '.$pdo->quote($receipt['nonce']));
            }
            $lease->assertOwned($app);
            $assert(DB::table('account_transactions')->count() > 0);
        });
    }

    private static function blockedByParent(int $pid): void
    {
        self::await(static fn (): bool => DB::selectOne('SELECT pg_backend_pid() = ANY(pg_blocking_pids(?)) AS blocked', [$pid])->blocked === true);
    }

    /** Poll a database condition with a deadline; time alone never means success. */
    private static function await(Closure $condition): void
    {
        $deadline = hrtime(true) + 3_000_000_000;
        do {
            if ($condition()) {
                return;
            }
            usleep(10_000);
        } while (hrtime(true) < $deadline);
        throw new RuntimeException('PostgreSQL synchronization deadline exceeded.');
    }

    private static function rollback(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    /** @return array{User, Team, FinancialAccount, AccountTransaction} */
    private static function transaction(): array
    {
        $actor = User::factory()->create();
        $team = $actor->personalTeam();
        $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
        $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, ['amount' => '123.12345678',
            'kind' => 'income', 'status' => 'posted', 'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02']);

        return [$actor, $team, $account, $transaction];
    }

    /** @return array{User, Team, TransactionTransfer} */
    private static function transfer(string $type): array
    {
        $actor = User::factory()->create();
        $team = $actor->personalTeam();
        $source = FinancialAccount::factory()->create(['team_id' => $team->id]);
        $destination = FinancialAccount::factory()->create(['team_id' => $team->id,
            'type' => $type === 'liability' ? 'credit_card' : 'savings', 'currency_code' => $type === 'cross-currency' ? 'INR' : 'USD']);
        $data = ['source_account_id' => $source->id, 'destination_account_id' => $destination->id,
            'source_amount' => '500.00000001', 'destination_amount' => $type === 'cross-currency' ? '42000.00000008' : '500.00000001',
            'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02'];
        if ($type === 'cross-currency') {
            $data['execution_fx'] = ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '84',
                'source' => 'synthetic-manual', 'effective_at' => '2026-10-02T00:00:00Z'];
        }
        $transfer = app(CreateTransfer::class)->handle($actor, $team, $data);

        return [$actor, $team, $transfer];
    }

    private static function audit(AccountTransaction $transaction, Closure $assert): void
    {
        $revision = $transaction->revisions()->sole();
        $snapshot = $revision->snapshot;
        $expected = $transaction->fresh()->canonicalSnapshot();
        ksort($snapshot);
        ksort($expected);
        $assert($revision->revision_number === 1 && $revision->operation->value === 'created'
            && $snapshot === $expected && $revision->actor_id !== null);
    }

    /** @return array<string, mixed> */
    private static function history(int $team): array
    {
        $snapshot = [];
        foreach (['account_transactions', 'transaction_transfers', 'transaction_revisions', 'account_balances'] as $table) {
            $snapshot[$table] = json_encode(DB::table($table)->where('team_id', $team)->orderBy('id')->get(), JSON_THROW_ON_ERROR);
        }

        return $snapshot;
    }
}
