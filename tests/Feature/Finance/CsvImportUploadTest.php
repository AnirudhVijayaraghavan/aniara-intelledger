<?php

use App\Actions\Finance\ParseCsvStatement;
use App\Actions\Finance\StoreEncryptedImportFile;
use App\Enums\TeamRole;
use App\Enums\TransactionImportBatchStatus;
use App\Enums\TransactionImportDisposition;
use App\Enums\TransactionImportRowStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use App\Models\TransactionImportRow;
use App\Models\TransactionRevision;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake(StoreEncryptedImportFile::DISK);
});

function csvImportRoute(FinancialAccount $account, ?Team $team = null): string
{
    return route('accounts.imports.store', ['current_team' => ($team ?? $account->team)->slug, 'financial_account' => $account->id]);
}

/** @return resource */
function csvImportStream(string $bytes)
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $bytes);
    rewind($stream);

    return $stream;
}

/** @return array{header: string, frames: list<string>} */
function csvEncryptedFrames(string $archive): array
{
    $offset = strlen(StoreEncryptedImportFile::MAGIC) + 16;
    $header = substr($archive, 0, $offset);
    $frames = [];
    while ($offset < strlen($archive)) {
        $length = unpack('Nsize', substr($archive, $offset, 4))['size'];
        $frames[] = substr($archive, $offset, 4 + $length);
        $offset += 4 + $length;
    }

    return ['header' => $header, 'frames' => $frames];
}

test('CSV upload stages ordered raw headers and cells privately without normalizing or changing financial data', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $balance->fresh()->getAttributes();
    $csv = "Date,Amount,Description\n31/10/2026,-000100.123456789,=UNTRUSTED()\nnot-a-date,not-money,Private account 1234567890\n";
    $response = $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('statement.csv', $csv)]);
    $response->assertCreated()->assertJsonPath('reused', false)->assertJsonPath('batch.status', 'mapping')
        ->assertJsonPath('batch.total_rows', 2)->assertJsonPath('batch.processed_rows', 0);
    $batch = TransactionImportBatch::sole();
    $rows = $batch->rows()->get();
    expect($batch->file_sha256)->toBe(hash('sha256', $csv));
    expect($batch->file_size)->toBe(strlen($csv));
    expect($batch->uploaded_by)->toBe($actor->id);
    expect($batch->original_filename)->toBe('statement.csv');
    expect($rows->pluck('record_number')->all())->toBe([2, 3]);
    expect($rows[0]->original_fields)->toBe(['headers' => ['Date', 'Amount', 'Description'], 'values' => ['31/10/2026', '-000100.123456789', '=UNTRUSTED()']]);
    expect($rows[1]->original_fields['values'])->toBe(['not-a-date', 'not-money', 'Private account 1234567890']);
    expect($rows[0]->normalized_data)->toBeNull();
    expect($rows[0]->status)->toBe(TransactionImportRowStatus::Pending);
    expect($batch->mapping)->toBeNull();
    expect($batch->approved_at)->toBeNull();
    expect(DB::table('transaction_import_rows')->value('original_fields'))->not->toContain('UNTRUSTED');
    expect($response->getContent())->not->toContain('file_path', 'file_disk', '1234567890', 'statement.csv', 'transaction-imports', 'original_fields');
    expect(config('filesystems.disks.'.StoreEncryptedImportFile::DISK.'.serve'))->toBeFalse();
    expect(str_starts_with(str_replace('\\', '/', config('filesystems.disks.'.StoreEncryptedImportFile::DISK.'.root')), str_replace('\\', '/', config('filesystems.disks.local.root')).'/'))->toBeFalse();
    $encrypted = Storage::disk(StoreEncryptedImportFile::DISK)->get($batch->file_path);
    expect($encrypted)->toStartWith(StoreEncryptedImportFile::MAGIC)->not->toContain('1234567890', 'UNTRUSTED', 'Date,Amount');
    $stream = csvImportStream($encrypted);
    try {
        expect(implode('', iterator_to_array(app(StoreEncryptedImportFile::class)->read($stream, $batch->file_sha256))))->toBe($csv);
    } finally {
        fclose($stream);
    }
    expect(Storage::disk('public')->exists($batch->file_path))->toBeFalse();
    expect($balance->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
    $this->assertDatabaseCount('transaction_source_references', 0);
});

test('quoted commas escaped quotes BOM and multiline UTF8 records preserve logical source numbers', function (string $ending) {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $csv = "\xEF\xBB\xBFDate,Description,Amount".$ending
        .'2026-10-01,"First, ""quoted""'.$ending.'भारत line",100.00000000'.$ending
        .'2026-10-02,"Next",-25';
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', $csv)])
        ->assertCreated()->assertJsonPath('batch.total_rows', 2);
    $rows = TransactionImportRow::orderBy('record_number')->get();
    expect($rows->pluck('record_number')->all())->toBe([2, 3]);
    expect($rows[0]->original_fields['headers'])->toBe(['Date', 'Description', 'Amount']);
    expect($rows[0]->original_fields['values'][1])->toBe('First, "quoted"'.$ending.'भारत line');
    expect(TransactionImportBatch::sole()->file_sha256)->toBe(hash('sha256', $csv));
})->with(["\n", "\r\n"]);

test('duplicate header names and empty quoted values retain positional identity', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "Value,Value,Note\n01,02,\"\"")])->assertCreated();
    expect(TransactionImportRow::sole()->original_fields)->toBe(['headers' => ['Value', 'Value', 'Note'], 'values' => ['01', '02', '']]);
});

test('invalid CSV structures encoding and bounds return sanitized errors with no staged residue', function (string $csv, string $expected) {
    Log::spy();
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $response = $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', $csv)]);
    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    expect($response->json('errors.file.0'))->toContain($expected);
    expect($response->getContent())->not->toContain('SECRET_VALUE', 'Private account');
    $this->assertDatabaseCount('transaction_import_batches', 0);
    $this->assertDatabaseCount('transaction_import_rows', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('info');
})->with([
    'unterminated quote' => ["Date,Note\n1,\"SECRET_VALUE", 'record 2'],
    'unexpected closing suffix' => ["Date,Note\n1,\"SECRET_VALUE\"tail", 'closing quote'],
    'quote in plain field' => ["Date,Note\n1,SECRET_VALUE\"bad", 'Quotes must'],
    'column mismatch' => ["Date,Note\nSECRET_VALUE", 'column count'],
    'empty header' => ["Date,\n1,2", 'Header names'],
    'header only' => ["Date,Note\n", 'at least one'],
    'invalid UTF8' => ["Date,Note\n1,\xC3\x28", 'UTF-8'],
    'unsupported control' => ["Date,Note\n1,\x01", 'control characters'],
    'bare carriage return' => ["Date,Note\r1,2", 'line endings'],
    'field limit' => ["Note\n".str_repeat('x', 8193), '8 KiB'],
    'record limit' => [implode(',', array_fill(0, 9, 'Column'))."\n".implode(',', array_fill(0, 9, str_repeat('x', 8000))), '64 KiB'],
    'column limit' => [implode(',', array_fill(0, 65, 'Column'))."\n1", '64 columns'],
]);

test('empty binary and incorrectly named uploads are rejected before staging', function (string $name, string $content) {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent($name, $content)])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseCount('transaction_import_batches', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
})->with([
    'empty' => ['empty.csv', ''],
    'PHP disguised as CSV' => ['bank.csv', "<?php echo 'secret';"],
    'PDF' => ['bank.csv', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>"],
    'wrong extension' => ['bank.txt', "Date,Amount\n2026-10-01,10"],
    'missing extension' => ['bank', "Date,Amount\n2026-10-01,10"],
]);

test('file uploads exceeding 10 MiB and injected tenant metadata are rejected', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->create('large.csv', 10241, 'text/csv')])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2"), 'team_id' => 999, 'mapping' => ['amount' => 'B']])
        ->assertUnprocessable()->assertJsonValidationErrors(['team_id', 'mapping']);
    $this->assertDatabaseCount('transaction_import_batches', 0);
});

test('25,000 data records stage in bounded inserts and an extra record rolls the attempt back', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $csv = "A,B\n".str_repeat("1,2\n", 25000);
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('limit.csv', $csv)])
        ->assertCreated()->assertJsonPath('batch.total_rows', 25000);
    expect(TransactionImportRow::count())->toBe(25000);
    expect(TransactionImportRow::max('record_number'))->toBe(25001);
    $original = TransactionImportBatch::sole()->fresh()->getAttributes();
    $files = Storage::disk(StoreEncryptedImportFile::DISK)->allFiles();
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('too-many.csv', $csv."1,2\n")])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(TransactionImportRow::count())->toBe(25000);
    expect(TransactionImportBatch::sole()->getAttributes())->toBe($original);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe($files);
});

test('valid multibyte fields spanning read chunks retain their exact maximum-length bytes', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $value = str_repeat('a', 8189).'₹';
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('unicode.csv', "Text\n".$value)])
        ->assertCreated();
    expect(TransactionImportRow::sole()->original_fields['values'])->toBe([$value]);
});

test('CSV records are yielded before the whole stream is read and escaped quotes may cross chunks', function () {
    $csv = "A,B\n".str_repeat('x', 8180).',"12345""tail"'."\n".str_repeat("1,2\n", 5000);
    $stream = csvImportStream($csv);
    try {
        $records = app(ParseCsvStatement::class)->records($stream);
        expect($records->current()['fields'])->toBe(['A', 'B']);
        expect(ftell($stream))->toBeLessThan(strlen($csv));
        $records->next();
        expect($records->current()['fields'][1])->toBe('12345"tail');
    } finally {
        fclose($stream);
    }
});

test('the streaming parser independently enforces the byte limit without collecting records', function () {
    $stream = fopen('php://temp/maxmemory:1024', 'w+b');
    try {
        fwrite($stream, "A\n");
        $line = str_repeat('x', 1023)."\n";
        for ($index = 0; $index < 11000; $index++) {
            fwrite($stream, $line);
        }
        rewind($stream);
        $recordsRead = 0;
        expect(function () use ($stream, &$recordsRead): void {
            foreach (app(ParseCsvStatement::class)->records($stream) as $record) {
                $recordsRead++;
            }
        })->toThrow(ValidationException::class, '10 MiB');
        expect($recordsRead)->toBeGreaterThan(0)->toBeLessThan(11000);
    } finally {
        fclose($stream);
    }
});

test('only owners and admins can upload to household accounts', function (?TeamRole $role, int $status) {
    $account = FinancialAccount::factory()->create();
    $actor = User::factory()->create();
    if ($role !== null) {
        $account->team->members()->attach($actor, ['role' => $role->value]);
    }
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")])->assertStatus($status);
    $this->assertDatabaseCount('transaction_import_batches', $status === 201 ? 1 : 0);
})->with([[TeamRole::Owner, 201], [TeamRole::Admin, 201], [TeamRole::Member, 403], [null, 403]]);

test('guests archived accounts and cross team routes cannot stage uploads', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $payload = fn () => ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")];
    $this->postJson(csvImportRoute($account), $payload())->assertUnauthorized();
    $foreign = FinancialAccount::factory()->create();
    $this->actingAs($actor)->postJson(csvImportRoute($foreign, $account->team), $payload())->assertNotFound();
    $account->update(['archived_at' => now()]);
    $this->postJson(csvImportRoute($account), $payload())->assertForbidden();
    $this->assertDatabaseCount('transaction_import_batches', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
});

test('upload authorization uses the explicit route household rather than current team preference', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $other = Team::factory()->create();
    $other->members()->attach($actor, ['role' => 'owner']);
    $actor->switchTeam($other);
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")])->assertCreated();
    expect(TransactionImportBatch::sole()->team_id)->toBe($account->team_id);
});

test('repeat uploads return existing approved outcomes without modifying source rows or files', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $csv = "Date,Amount\n2026-10-01,100\n";
    $upload = fn (string $name) => $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent($name, $csv)]);
    $upload('first.csv')->assertCreated();
    $batch = TransactionImportBatch::sole();
    $digest = hash('sha256', 'approved snapshot');
    $batch->update(['status' => TransactionImportBatchStatus::Approved, 'preview_digest' => $digest,
        'approved_preview_digest' => $digest, 'approved_mapping_version' => 1, 'approved_at' => now(), 'approved_by' => $actor->id]);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $row = $batch->rows()->sole();
    $row->update(['account_transaction_id' => $transaction->id, 'applied_revision_number' => 1, 'processed_at' => now(),
        'status' => TransactionImportRowStatus::Imported, 'disposition' => TransactionImportDisposition::Import]);
    $before = $batch->fresh()->getAttributes();
    $beforeRow = $row->fresh()->getAttributes();
    $revisions = TransactionRevision::get()->toArray();
    $files = Storage::disk(StoreEncryptedImportFile::DISK)->allFiles();
    $upload('renamed.csv')->assertOk()->assertJsonPath('reused', true)->assertJsonPath('batch.id', $batch->id)->assertJsonPath('batch.status', 'approved');
    expect($batch->fresh()->getAttributes())->toBe($before);
    expect($row->fresh()->getAttributes())->toBe($beforeRow);
    expect(TransactionRevision::get()->toArray())->toBe($revisions);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe($files);
    $this->assertDatabaseCount('transaction_import_batches', 1);
});

test('a missing reloaded batch returns 422 cleans up staging and allows an idempotent retry without changing prior outcomes', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '100.12345678']);
    $beforeBalance = $balance->fresh()->getAttributes();
    $upload = fn (string $csv) => $this->actingAs($actor)->postJson(csvImportRoute($account), [
        'file' => UploadedFile::fake()->createWithContent('bank.csv', $csv),
    ]);
    $upload("Date,Amount\n2026-10-01,100\n")->assertCreated();
    $existing = TransactionImportBatch::sole();
    $digest = hash('sha256', 'existing approved preview');
    $existing->update(['status' => TransactionImportBatchStatus::Approved, 'preview_digest' => $digest,
        'approved_preview_digest' => $digest, 'approved_mapping_version' => 1, 'approved_at' => now(), 'approved_by' => $actor->id]);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $row = $existing->rows()->sole();
    $row->update(['account_transaction_id' => $transaction->id, 'applied_revision_number' => 1, 'processed_at' => now(),
        'status' => TransactionImportRowStatus::Imported, 'disposition' => TransactionImportDisposition::Import]);
    $beforeBatches = DB::table('transaction_import_batches')->orderBy('id')->get()->toArray();
    $beforeRows = DB::table('transaction_import_rows')->orderBy('id')->get()->toArray();
    $beforeRevisions = DB::table('transaction_revisions')->orderBy('id')->get()->toArray();
    $beforeFile = Storage::disk(StoreEncryptedImportFile::DISK)->get($existing->file_path);
    $csv = "Date,Amount,Description\n2026-10-02,-10.12345678,PRIVATE_VALUE\n";
    $event = 'eloquent.updated: '.TransactionImportBatch::class;
    Event::listen($event, function (TransactionImportBatch $batch) use ($csv): void {
        if ($batch->file_sha256 === hash('sha256', $csv)) {
            DB::table('transaction_import_rows')->where('transaction_import_batch_id', $batch->id)->delete();
            DB::table('transaction_import_batches')->where('id', $batch->id)->delete();
        }
    });

    try {
        $response = $upload($csv);
        $response->assertUnprocessable()->assertJsonValidationErrors('file');
        expect($response->json('errors.file.0'))->toBe('The CSV could not be staged. Please retry or contact an administrator.');
        expect($response->getContent())->not->toContain('PRIVATE_VALUE', $existing->file_path, 'bank.csv');
    } finally {
        Event::forget($event);
    }

    expect(DB::table('transaction_import_batches')->orderBy('id')->get()->toArray())->toEqual($beforeBatches);
    expect(DB::table('transaction_import_rows')->orderBy('id')->get()->toArray())->toEqual($beforeRows);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([$existing->file_path]);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->get($existing->file_path))->toBe($beforeFile);

    $response = $upload($csv)->assertCreated()->assertJsonPath('reused', false)->assertJsonPath('batch.total_rows', 1);
    $batchId = $response->json('batch.id');
    $upload($csv)->assertOk()->assertJsonPath('reused', true)->assertJsonPath('batch.id', $batchId);

    $batch = TransactionImportBatch::findOrFail($batchId);
    expect($batch->rows()->sole()->original_fields['values'])->toBe(['2026-10-02', '-10.12345678', 'PRIVATE_VALUE']);
    expect(DB::table('transaction_import_rows')->where('transaction_import_batch_id', $batchId)->value('original_fields'))->not->toContain('PRIVATE_VALUE');
    expect(DB::table('transaction_import_batches')->where('id', $existing->id)->get()->toArray())->toEqual($beforeBatches);
    expect(DB::table('transaction_import_rows')->where('id', $row->id)->get()->toArray())->toEqual($beforeRows);
    expect(DB::table('transaction_revisions')->orderBy('id')->get()->toArray())->toEqual($beforeRevisions);
    expect($balance->fresh()->getAttributes())->toBe($beforeBalance);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toHaveCount(2);
    $this->assertDatabaseCount('transaction_import_batches', 2);
    $this->assertDatabaseCount('transaction_import_rows', 2);
    $this->assertDatabaseCount('account_transactions', 1);
});

test('the same bytes may be staged separately for different explicitly authorized accounts', function () {
    $actor = User::factory()->create();
    $first = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $second = FinancialAccount::factory()->create(['team_id' => $first->team_id]);
    foreach ([$first, $second] as $account) {
        $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")])->assertCreated();
    }
    expect(TransactionImportBatch::count())->toBe(2);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toHaveCount(2);
});

test('late malformed records roll back previously flushed staging chunks', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $csv = "A,B\n".str_repeat("1,2\n", 65).'1,"SECRET_VALUE';
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', $csv)])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseCount('transaction_import_batches', 0);
    $this->assertDatabaseCount('transaction_import_rows', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
});

test('partial storage failures remove files and return sanitized errors after rolling back staging', function () {
    $this->app->instance(StoreEncryptedImportFile::class, new class extends StoreEncryptedImportFile
    {
        public function handle($source, string $path): array
        {
            Storage::disk(self::DISK)->put($path, 'partial ciphertext');
            throw new RuntimeException('SECRET_VALUE account 1234567890 internal/path');
        }
    });
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $response = $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")]);
    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    expect($response->getContent())->not->toContain('SECRET_VALUE', '1234567890', 'internal/path');
    $this->assertDatabaseCount('transaction_import_batches', 0);
    $this->assertDatabaseCount('transaction_import_rows', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
});

test('database failure after encrypted storage removes the archive and rolls back all staging data', function () {
    Event::listen('eloquent.updating: '.TransactionImportBatch::class, function (): never {
        throw new RuntimeException('Injected final save failure');
    });
    try {
        $actor = User::factory()->create();
        $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
        $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")])->assertUnprocessable();
    } finally {
        Event::forget('eloquent.updating: '.TransactionImportBatch::class);
    }
    $this->assertDatabaseCount('transaction_import_batches', 0);
    $this->assertDatabaseCount('transaction_import_rows', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
});

test('source hash mismatches after storage cannot commit staging records or retain the archive', function () {
    $this->app->instance(StoreEncryptedImportFile::class, new class extends StoreEncryptedImportFile
    {
        public function handle($source, string $path): array
        {
            $result = parent::handle($source, $path);
            $result['sha256'] = hash('sha256', 'different content');

            return $result;
        }
    });
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    $this->actingAs($actor)->postJson(csvImportRoute($account), ['file' => UploadedFile::fake()->createWithContent('bank.csv', "A,B\n1,2")])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseCount('transaction_import_batches', 0);
    $this->assertDatabaseCount('transaction_import_rows', 0);
    expect(Storage::disk(StoreEncryptedImportFile::DISK)->allFiles())->toBe([]);
});

test('encrypted frame order truncation and file mixing cannot be accepted', function (string $mutation) {
    $source = csvImportStream(str_repeat('private value ', 12000));
    $files = app(StoreEncryptedImportFile::class);
    try {
        $files->handle($source, 'original.ilcsv');
    } finally {
        fclose($source);
    }
    $archive = Storage::disk(StoreEncryptedImportFile::DISK)->get('original.ilcsv');
    $parts = csvEncryptedFrames($archive);
    if ($mutation === 'reorder') {
        [$parts['frames'][0], $parts['frames'][1]] = [$parts['frames'][1], $parts['frames'][0]];
    } elseif ($mutation === 'truncate') {
        array_pop($parts['frames']);
    } elseif ($mutation === 'mix') {
        $parts['header'] = StoreEncryptedImportFile::MAGIC.random_bytes(16);
    } elseif ($mutation === 'tamper') {
        $parts['frames'][0][15] = $parts['frames'][0][15] === 'A' ? 'B' : 'A';
    }
    $stream = csvImportStream($parts['header'].implode('', $parts['frames']).($mutation === 'append' ? 'x' : ''));
    try {
        expect(fn () => iterator_to_array($files->read($stream, hash('sha256', str_repeat('private value ', 12000)))))->toThrow($mutation === 'tamper' ? DecryptException::class : RuntimeException::class);
    } finally {
        fclose($stream);
    }
})->with(['reorder', 'truncate', 'mix', 'tamper', 'append']);

test('a different valid encrypted archive cannot substitute for a batch source', function () {
    $files = app(StoreEncryptedImportFile::class);
    $source = csvImportStream('different source bytes');
    try {
        $files->handle($source, 'different.ilcsv');
    } finally {
        fclose($source);
    }
    $encrypted = csvImportStream(Storage::disk(StoreEncryptedImportFile::DISK)->get('different.ilcsv'));
    try {
        expect(fn () => iterator_to_array($files->read($encrypted, hash('sha256', 'original source bytes'))))->toThrow(RuntimeException::class);
    } finally {
        fclose($encrypted);
    }
});
