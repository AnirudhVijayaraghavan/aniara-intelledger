<?php

namespace App\Actions\Finance;

use App\Enums\TransactionImportBatchStatus;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use App\Models\TransactionImportRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadTransactionImport
{
    public function __construct(private ParseCsvStatement $parser, private StoreEncryptedImportFile $files) {}

    /** @return array{batch: TransactionImportBatch, reused: bool} */
    public function handle(User $actor, Team $team, FinancialAccount $account, UploadedFile $file): array
    {
        $account = $team->financialAccounts()->whereKey($account->id)->firstOrFail();
        Gate::forUser($actor)->authorize('create', [TransactionImportBatch::class, $account]);
        $source = @fopen($file->getPathname(), 'rb');
        if ($source === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be read.']);
        }
        $storedPath = null;
        try {
            $hash = hash_init('sha256');
            $size = hash_update_stream($hash, $source, ParseCsvStatement::MAX_FILE_BYTES + 1);
            if ($size > ParseCsvStatement::MAX_FILE_BYTES) {
                throw ValidationException::withMessages(['file' => 'The CSV exceeds the 10 MiB limit.']);
            }
            if ($size !== $file->getSize()) {
                throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be read completely.']);
            }
            $sha256 = hash_final($hash);
            rewind($source);

            return DB::transaction(function () use ($actor, $team, $account, $file, $source, $size, $sha256, &$storedPath): array {
                $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
                $account = $team->financialAccounts()->whereKey($account->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [TransactionImportBatch::class, $account]);
                $existing = $account->transactionImportBatches()->forTeam($team)->where('file_sha256', $sha256)->first();
                if ($existing !== null) {
                    return ['batch' => $existing, 'reused' => true];
                }
                $path = $team->id.'/'.$account->id.'/'.Str::uuid().'.ilcsv';
                $batch = $account->transactionImportBatches()->create([
                    'team_id' => $team->id, 'file_sha256' => $sha256, 'file_disk' => StoreEncryptedImportFile::DISK,
                    'file_path' => $path, 'original_filename' => $file->getClientOriginalName(), 'file_size' => $size,
                    'uploaded_by' => $actor->id, 'parser_version' => 'csv-utf8-v1', 'expires_at' => now()->addDays(30),
                ]);
                $headers = [];
                $buffer = [];
                $bufferBytes = 0;
                $count = 0;
                foreach ($this->parser->records($source) as $record) {
                    if ($record['number'] === 1) {
                        $headers = $record['fields'];

                        continue;
                    }
                    $row = $batch->rows()->make([
                        'team_id' => $team->id, 'financial_account_id' => $account->id,
                        'record_number' => $record['number'], 'mapping_version' => $batch->mapping_version,
                        'original_fields' => ['headers' => $headers, 'values' => $record['fields']],
                    ]);
                    $row->setCreatedAt($row->freshTimestamp())->setUpdatedAt($row->freshTimestamp());
                    $attributes = $row->getAttributes();
                    $bytes = strlen($attributes['original_fields']);
                    if ($buffer !== [] && (count($buffer) >= 50 || $bufferBytes + $bytes > 524288)) {
                        $this->insertRows($buffer);
                        $buffer = [];
                        $bufferBytes = 0;
                    }
                    $buffer[] = $attributes;
                    $bufferBytes += $bytes;
                    $count++;
                }
                if ($buffer !== []) {
                    $this->insertRows($buffer);
                }
                rewind($source);
                $storedPath = $path;
                $stored = $this->files->handle($source, $path);
                if ($stored['sha256'] !== $sha256 || $stored['size'] !== $size) {
                    throw new RuntimeException('The uploaded file changed during staging.');
                }
                $batch->fill(['status' => TransactionImportBatchStatus::Mapping, 'total_rows' => $count])->save();

                $freshBatch = $batch->fresh();
                if ($freshBatch === null) {
                    throw new RuntimeException('The staged import batch could not be reloaded.');
                }

                return ['batch' => $freshBatch, 'reused' => false];
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                try {
                    if (! Storage::disk(StoreEncryptedImportFile::DISK)->delete($storedPath)) {
                        throw new RuntimeException('Import cleanup failed.');
                    }
                } catch (Throwable) {
                    throw new RuntimeException('CSV upload failed; encrypted file cleanup requires administrator attention.');
                }
            }
            if ($exception instanceof ValidationException || $exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
                throw $exception;
            }

            throw ValidationException::withMessages(['file' => 'The CSV could not be staged. Please retry or contact an administrator.']);
        } finally {
            fclose($source);
        }
    }

    /**
     * Only new, server-owned staging rows are inserted. Model casts encrypt the
     * source fields before batching; no transaction or audit tables are written.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(array $rows): void
    {
        TransactionImportRow::query()->insert($rows);
    }
}
