<?php

/**
 * Run explicitly: php tests/PostgreSqlImportFoundationProbe.php
 * Only migrates a newly created PostgreSQL database after verifying its ownership receipt.
 * Does not use RefreshDatabase or change the normal SQLite-only test guard.
 */

use App\Actions\Finance\StoreEncryptedImportFile;
use App\Actions\Finance\UploadTransactionImport;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\TransactionImportBatch;
use App\Models\TransactionImportRow;
use App\Models\TransactionRevision;
use App\Models\TransactionSourceReference;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';

function pgImportAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, array<string, mixed>> */
function pgImportSnapshot(PDO $pdo): array
{
    $result = [];
    foreach ($pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $result[$table] = $pdo->query('SELECT COUNT(*) AS count, md5(COALESCE(string_agg(to_jsonb(t)::text, chr(10) ORDER BY to_jsonb(t)::text), \'\')) AS digest FROM public.'.$quoted.' t')->fetch(PDO::FETCH_ASSOC);
    }

    return $result;
}

/** @param Closure(): mixed $operation */
function pgImportReject(Closure $operation, string $sqlstate): void
{
    try {
        DB::transaction($operation);
        throw new RuntimeException('Expected PostgreSQL constraint rejection.');
    } catch (QueryException $exception) {
        pgImportAssert(($exception->errorInfo[0] ?? null) === $sqlstate, 'Unexpected PostgreSQL constraint result: '.($exception->errorInfo[0] ?? 'unknown').' instead of '.$sqlstate.'.');
    }
}

$stage = 'bootstrap';
$admin = null;
$ownedDatabase = null;
$ownedOid = null;
$nonce = bin2hex(random_bytes(16));
$checks = 0;
$exitCode = 0;
$uploadRoot = null;

try {
    pgImportAssert(PHP_SAPI === 'cli', 'This integration probe is CLI-only.');
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
    pgImportAssert($app->environment('local') && ($config['driver'] ?? null) === 'pgsql'
        && ! isset($config['read']) && ! isset($config['write']) && ! isset($config['direct']), 'Local single-endpoint PostgreSQL configuration is required.');

    $stage = 'disposable database provisioning';
    $admin = DB::getPdo();
    $developmentDatabase = $admin->query('SELECT current_database()')->fetchColumn();
    $before = pgImportSnapshot($admin);
    $database = 'intelledger_pg_import_'.bin2hex(random_bytes(6));
    pgImportAssert(preg_match('/\Aintelledger_pg_import_[a-f0-9]{12}\z/', $database) === 1
        && $database !== $developmentDatabase && $database !== $config['database'], 'Invalid disposable database name.');
    $statement = $admin->prepare('SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname=?)');
    $statement->execute([$database]);
    pgImportAssert(! $statement->fetchColumn(), 'Disposable database must not already exist.');
    $admin->exec('CREATE DATABASE "'.$database.'" TEMPLATE template0');
    $ownedDatabase = $database;
    $statement = $admin->prepare('SELECT oid FROM pg_database WHERE datname=?');
    $statement->execute([$database]);
    $ownedOid = (int) $statement->fetchColumn();
    $targetConfig = $config;
    unset($targetConfig['url'], $targetConfig['read'], $targetConfig['write'], $targetConfig['direct']);
    $targetConfig['database'] = $database;
    $target = (new PostgresConnector)->connect($targetConfig);
    pgImportAssert($target->query('SELECT current_database()')->fetchColumn() === $database, 'Disposable target mismatch.');
    $target->exec('CREATE TABLE __intelledger_import_receipt (token TEXT PRIMARY KEY)');
    $target->prepare('INSERT INTO __intelledger_import_receipt(token) VALUES (?)')->execute([$nonce]);
    $target = null;
    DB::purge();
    config(['database.default' => 'pg_import_validation', 'database.connections' => ['pg_import_validation' => $targetConfig],
        'cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
    DB::setDefaultConnection('pg_import_validation');
    $identity = DB::selectOne('SELECT current_database() AS name, oid FROM pg_database WHERE datname=current_database()');
    pgImportAssert(DB::getDriverName() === 'pgsql' && $identity->name === $database && (int) $identity->oid === $ownedOid
        && DB::table('__intelledger_import_receipt')->value('token') === $nonce, 'Disposable database ownership proof failed.');
    DB::statement("SET lock_timeout='10s'");
    DB::statement("SET statement_timeout='30s'");

    $stage = 'baseline schema and populated migration';
    $all = glob(__DIR__.'/../database/migrations/*.php');
    $imports = array_values(array_filter($all, fn (string $path): bool => preg_match('/create_transaction_(import_batches|import_rows|source_references)_table\.php$/', $path) === 1));
    pgImportAssert(count($imports) === 3, 'Expected exactly three import migrations.');
    $baseline = array_values(array_diff($all, $imports));
    pgImportAssert(Artisan::call('migrate', ['--database' => 'pg_import_validation', '--path' => $baseline, '--realpath' => true, '--force' => true, '--no-interaction' => true]) === 0, 'Baseline migration failed.');
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $financialBefore = pgImportSnapshot(DB::getPdo());
    pgImportAssert(Artisan::call('migrate', ['--database' => 'pg_import_validation', '--path' => $imports, '--realpath' => true, '--force' => true, '--no-interaction' => true]) === 0, 'Import migrations failed.');
    $after = pgImportSnapshot(DB::getPdo());
    foreach ($financialBefore as $table => $snapshot) {
        if ($table !== 'migrations') {
            pgImportAssert($after[$table] === $snapshot, 'Migration changed existing data.');
        }
    }
    $checks++;

    $stage = 'encrypted exact values and mapping history';
    $batch = TransactionImportBatch::factory()->approved()->create(['financial_account_id' => $account->id, 'mapping' => ['amount' => 'Debit']]);
    $row = TransactionImportRow::factory()->imported($transaction)->create([
        'transaction_import_batch_id' => $batch->id,
        'normalized_data' => ['amount' => '9999999999999999.12345678', 'currency_code' => 'USD'],
    ]);
    $reference = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'transaction_import_row_id' => $row->id]);
    pgImportAssert($row->fresh()->normalized_data['amount'] === '9999999999999999.12345678'
        && ! str_contains(DB::table('transaction_import_rows')->where('id', $row->id)->value('normalized_data'), '9999999999999999'), 'Encrypted decimal round trip failed.');
    $rowBefore = $row->fresh()->getAttributes();
    $batch = $batch->reviseMapping($actor, ['amount' => 'Credit'], 1, 'Correct mapping');
    pgImportAssert($batch->mapping_version === 2 && $batch->approved_at === null
        && $row->fresh()->getAttributes() === $rowBefore && $reference->fresh()->account_transaction_id === $transaction->id, 'Remapping rewrote committed provenance.');
    $checks++;

    $stage = 'uniqueness foreign keys and checks';
    pgImportReject(fn () => TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'source_key_hash' => $reference->source_key_hash]), '23505');
    pgImportReject(fn () => TransactionImportBatch::factory()->create(['financial_account_id' => $account->id, 'file_sha256' => $batch->file_sha256]), '23505');
    $other = FinancialAccount::factory()->create(['team_id' => $account->team_id]);
    pgImportReject(fn () => DB::table('transaction_import_rows')->where('id', $row->id)->update(['financial_account_id' => $other->id]), '23001');
    pgImportReject(fn () => DB::table('transaction_import_batches')->where('id', $batch->id)->update(['processed_rows' => -1]), '23514');
    pgImportReject(fn () => DB::table('transaction_import_rows')->where('id', $row->id)->update(['applied_revision_number' => 99]), '23503');
    $same = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    pgImportReject(fn () => DB::table('transaction_source_references')->where('id', $reference->id)->update(['account_transaction_id' => $same->id]), '23503');
    $fingerprint = hash('sha256', 'identical purchases');
    TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id, 'record_number' => 1000001, 'candidate_fingerprint' => $fingerprint]);
    TransactionImportRow::factory()->create(['transaction_import_batch_id' => $batch->id, 'record_number' => 1000002, 'candidate_fingerprint' => $fingerprint]);
    pgImportAssert($batch->rows()->where('candidate_fingerprint', $fingerprint)->count() === 2, 'Candidate fingerprints became unique.');
    $checks++;

    $stage = 'actor retention rollback and protected history';
    $recorder = User::factory()->create();
    $retained = TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id, 'recorded_by' => $recorder->id]);
    $recorder->delete();
    pgImportAssert($retained->fresh()->recorded_by === null, 'Actor deletion lost provenance.');
    $count = TransactionSourceReference::count();
    try {
        DB::transaction(function () use ($transaction): void {
            TransactionSourceReference::factory()->create(['account_transaction_id' => $transaction->id]);
            throw new RuntimeException('Injected rollback');
        });
    } catch (RuntimeException $exception) {
        pgImportAssert($exception->getMessage() === 'Injected rollback', 'Unexpected rollback failure.');
    }
    pgImportAssert(TransactionSourceReference::count() === $count && $balance->fresh()->amount === $balance->amount, 'Rollback or balance independence failed.');
    pgImportReject(fn () => DB::table('transaction_import_rows')->where('id', $row->id)->delete(), '23001');
    $checks++;

    $stage = 'CSV upload streaming encryption and idempotency';
    $uploadRoot = base_path('storage/framework/testing/pg-csv-'.bin2hex(random_bytes(8)));
    pgImportAssert(! file_exists($uploadRoot), 'Upload probe root must not already exist.');
    $diskConfig = (require base_path('config/filesystems.php'))['disks'][StoreEncryptedImportFile::DISK];
    $diskConfig['root'] = $uploadRoot;
    config(['filesystems.disks.'.StoreEncryptedImportFile::DISK => $diskConfig]);
    Storage::forgetDisk(StoreEncryptedImportFile::DISK);
    $financial = [AccountTransaction::orderBy('id')->get()->toArray(), AccountBalance::orderBy('id')->get()->toArray(), TransactionRevision::orderBy('id')->get()->toArray()];
    $csv = "\xEF\xBB\xBFDate,Description,Amount\r\n2026-10-01,\"Quoted, \"\"value\"\"\r\nnext line\",001.230000000\r\n";
    $uploader = app(UploadTransactionImport::class);
    $uploaded = $uploader->handle($actor, $account->team, $account, UploadedFile::fake()->createWithContent('bank.csv', $csv));
    $uploadedBatch = $uploaded['batch'];
    $staged = $uploadedBatch->rows()->sole();
    pgImportAssert(! $uploaded['reused'] && $uploadedBatch->total_rows === 1 && $staged->record_number === 2
        && $staged->normalized_data === null && $staged->original_fields['values'][2] === '001.230000000', 'PostgreSQL raw staging did not preserve source text.');
    $encrypted = Storage::disk(StoreEncryptedImportFile::DISK)->readStream($uploadedBatch->file_path);
    try {
        pgImportAssert(implode('', iterator_to_array(app(StoreEncryptedImportFile::class)->read($encrypted, $uploadedBatch->file_sha256))) === $csv, 'Encrypted source round trip failed.');
    } finally {
        fclose($encrypted);
    }
    $snapshot = $uploadedBatch->fresh()->getAttributes();
    $repeated = $uploader->handle($actor, $account->team, $account, UploadedFile::fake()->createWithContent('copy.csv', $csv));
    pgImportAssert($repeated['reused'] && $repeated['batch']->id === $uploadedBatch->id
        && $uploadedBatch->fresh()->getAttributes() === $snapshot, 'Repeated CSV upload changed existing provenance.');
    $checks++;

    $stage = 'CSV staging rollback and file cleanup';
    $batchCount = TransactionImportBatch::count();
    $rowCount = TransactionImportRow::count();
    $storedFiles = Storage::disk(StoreEncryptedImportFile::DISK)->allFiles();
    try {
        $uploader->handle($actor, $account->team, $account, UploadedFile::fake()->createWithContent('bad.csv', "A,B\n".str_repeat("1,2\n", 65).'1,"unclosed'));
        throw new RuntimeException('Malformed upload was accepted.');
    } catch (ValidationException $exception) {
        pgImportAssert(isset($exception->errors()['file']), 'Missing CSV validation feedback.');
    }
    $event = 'eloquent.updating: '.TransactionImportBatch::class;
    Event::listen($event, function (): never {
        throw new RuntimeException('Injected CSV final-save failure');
    });
    try {
        try {
            $uploader->handle($actor, $account->team, $account, UploadedFile::fake()->createWithContent('failure.csv', "A,B\n1,2"));
            throw new RuntimeException('CSV failure injection was ignored.');
        } catch (ValidationException $exception) {
            pgImportAssert(isset($exception->errors()['file']), 'Missing sanitized failure feedback.');
        }
    } finally {
        Event::forget($event);
    }
    pgImportAssert(TransactionImportBatch::count() === $batchCount && TransactionImportRow::count() === $rowCount
        && Storage::disk(StoreEncryptedImportFile::DISK)->allFiles() === $storedFiles, 'Failed CSV left partial staging or files.');
    pgImportAssert([AccountTransaction::orderBy('id')->get()->toArray(), AccountBalance::orderBy('id')->get()->toArray(), TransactionRevision::orderBy('id')->get()->toArray()] === $financial, 'CSV staging changed financial history.');
    $checks++;
    pgImportAssert(pgImportSnapshot($admin) === $before, 'Development data or migration history changed.');
    echo json_encode(['result' => 'passed', 'checks' => $checks, 'developmentUnchanged' => true]).PHP_EOL;
} catch (Throwable $exception) {
    $exitCode = 1;
    echo json_encode(['result' => 'failed', 'stage' => $stage, 'exception' => get_class($exception),
        'detail' => get_class($exception) === RuntimeException::class ? $exception->getMessage() : null,
        'sqlstate' => $exception instanceof QueryException || $exception instanceof PDOException ? ($exception->errorInfo[0] ?? $exception->getCode()) : null]).PHP_EOL;
} finally {
    if ($uploadRoot !== null) {
        try {
            pgImportAssert(str_starts_with(str_replace('\\', '/', $uploadRoot), str_replace('\\', '/', base_path('storage/framework/testing')).'/')
                && preg_match('/\Apg-csv-[a-f0-9]{16}\z/', basename($uploadRoot)) === 1
                && config('filesystems.disks.'.StoreEncryptedImportFile::DISK.'.root') === $uploadRoot, 'Unexpected upload cleanup target.');
            foreach (Storage::disk(StoreEncryptedImportFile::DISK)->allFiles() as $path) {
                pgImportAssert(Storage::disk(StoreEncryptedImportFile::DISK)->delete($path), 'Upload test file cleanup failed.');
            }
            echo json_encode(['disposableUploadFilesRemoved' => true]).PHP_EOL;
        } catch (Throwable) {
            $exitCode = 1;
            echo json_encode(['uploadCleanupFailed' => true]).PHP_EOL;
        }
    }
    if ($ownedDatabase !== null && $admin !== null) {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            pgImportAssert(preg_match('/\Aintelledger_pg_import_[a-f0-9]{12}\z/', $ownedDatabase) === 1 && $ownedDatabase !== $developmentDatabase, 'Unexpected cleanup target.');
            $statement = $admin->prepare('SELECT oid FROM pg_database WHERE datname=?');
            $statement->execute([$ownedDatabase]);
            pgImportAssert($ownedOid !== null && (int) $statement->fetchColumn() === $ownedOid, 'Cleanup database ownership changed.');
            if ($ownedOid !== null) {
                pgImportAssert(DB::getDatabaseName() === $ownedDatabase && DB::table('__intelledger_import_receipt')->value('token') === $nonce, 'Cleanup receipt mismatch.');
            }
            DB::purge('pg_import_validation');
            $admin->exec('DROP DATABASE "'.$ownedDatabase.'"');
            echo json_encode(['disposableDatabaseRemoved' => true]).PHP_EOL;
        } catch (Throwable) {
            $exitCode = 1;
            echo json_encode(['cleanupFailed' => true, 'disposableDatabase' => $ownedDatabase]).PHP_EOL;
        }
    }
}

exit($exitCode);
