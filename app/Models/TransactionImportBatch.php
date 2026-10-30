<?php

namespace App\Models;

use App\Enums\TransactionImportBatchStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionImportBatchFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

/**
 * @property int $id
 * @property int $team_id
 * @property int $financial_account_id
 * @property int $mapping_version
 * @property int $preview_version
 * @property TransactionImportBatchStatus $status
 * @property array<string, mixed>|null $mapping
 * @property list<array<string, mixed>>|null $mapping_history
 * @property CarbonImmutable|null $approved_at
 * @property-read Team|null $team
 * @property-read FinancialAccount $account
 */
#[Fillable(['team_id', 'financial_account_id', 'source_namespace', 'file_sha256', 'file_disk', 'file_path', 'original_filename', 'file_size', 'parser_version', 'mapping_version', 'mapping', 'mapping_history', 'preview_version', 'preview_digest', 'status', 'total_rows', 'processed_rows', 'imported_rows', 'linked_rows', 'skipped_rows', 'failed_rows', 'uploaded_by', 'approved_by', 'approved_at', 'approved_mapping_version', 'approved_preview_digest', 'processing_started_at', 'processing_finished_at', 'expires_at', 'file_purged_at'])]
class TransactionImportBatch extends Model
{
    /** @use HasFactory<TransactionImportBatchFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['status' => 'uploaded', 'source_namespace' => 'csv', 'parser_version' => '1', 'mapping_version' => 1, 'preview_version' => 0];

    protected $hidden = ['file_disk', 'file_path', 'original_filename', 'file_sha256', 'mapping', 'mapping_history', 'preview_digest', 'approved_preview_digest'];

    private bool $revisingMapping = false;

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            $team = Team::whereKey($this->team_id)->lockForUpdate()->firstOrFail();
            FinancialAccount::forTeam($team)->whereKey($this->financial_account_id)->lockForUpdate()->firstOrFail();
            if ($this->exists) {
                $stored = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
                if ($stored->mapping_version !== $this->getOriginal('mapping_version') || $stored->status !== $this->getOriginal('status')) {
                    throw new DomainException('Reload the import batch before changing it.');
                }
                if ($this->isDirty(['team_id', 'financial_account_id', 'source_namespace', 'file_sha256', 'file_size', 'original_filename', 'parser_version', 'uploaded_by'])) {
                    throw new DomainException('Import file identity and provenance are immutable.');
                }
                if ($this->isDirty(['mapping', 'mapping_version', 'mapping_history']) && ! $this->revisingMapping) {
                    throw new DomainException('Use reviseMapping with an authorized actor to reprocess a file.');
                }
            } elseif ($this->mapping_version !== 1 || $this->mapping_history !== null) {
                throw new DomainException('New import batches start at mapping version one.');
            }
            if (preg_match('/\A[a-f0-9]{64}\z/', $this->file_sha256) !== 1) {
                throw new DomainException('Import files require a SHA-256 identity.');
            }

            return parent::save($options);
        });
    }

    /**
     * Version staging metadata only; never creates or resets transaction outcomes.
     *
     * @param  array<string, mixed>  $mapping
     */
    public function reviseMapping(User $actor, array $mapping, int $expectedVersion, string $reason): static
    {
        return $this->getConnection()->transaction(function () use ($actor, $mapping, $expectedVersion, $reason): static {
            $team = Team::whereKey($this->team_id)->lockForUpdate()->firstOrFail();
            $team->financialAccounts()->whereKey($this->financial_account_id)->lockForUpdate()->firstOrFail();
            $batch = static::query()->forTeam($team)->whereKey($this->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('reprocess', $batch);
            if ($batch->mapping_version !== $expectedVersion || trim($reason) === '' || mb_strlen($reason) > 2000) {
                throw new DomainException('Mapping changes require the current version and a reason of at most 2000 characters.');
            }
            $history = $batch->mapping_history ?? [];
            $history[] = [
                'mapping_version' => $batch->mapping_version, 'mapping' => $batch->mapping,
                'preview_version' => $batch->preview_version, 'preview_digest' => $batch->preview_digest,
                'approved_mapping_version' => $batch->approved_mapping_version,
                'approved_preview_digest' => $batch->approved_preview_digest,
                'approved_by' => $batch->approved_by, 'approved_at' => $batch->approved_at?->toISOString(),
                'changed_by' => $actor->id, 'changed_at' => now()->utc()->toISOString(), 'reason' => $reason,
            ];
            $batch->revisingMapping = true;
            $batch->fill([
                'mapping' => $mapping, 'mapping_history' => $history, 'mapping_version' => $batch->mapping_version + 1,
                'preview_version' => $batch->preview_version + 1, 'preview_digest' => null,
                'approved_by' => null, 'approved_at' => null, 'approved_mapping_version' => null, 'approved_preview_digest' => null,
                'status' => TransactionImportBatchStatus::Mapping, 'processing_started_at' => null, 'processing_finished_at' => null,
            ]);
            try {
                $batch->save();
            } finally {
                $batch->revisingMapping = false;
            }

            return $batch;
        });
    }

    public function delete(): never
    {
        throw new DomainException('Import provenance cannot be deleted.');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<TransactionImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(TransactionImportRow::class)->orderBy('record_number');
    }

    /** @param Builder<TransactionImportBatch> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'team_id' => 'integer', 'financial_account_id' => 'integer', 'file_size' => 'integer',
            'mapping_version' => 'integer', 'preview_version' => 'integer', 'approved_mapping_version' => 'integer',
            'total_rows' => 'integer', 'processed_rows' => 'integer', 'imported_rows' => 'integer',
            'linked_rows' => 'integer', 'skipped_rows' => 'integer', 'failed_rows' => 'integer',
            'status' => TransactionImportBatchStatus::class, 'mapping' => 'encrypted:array', 'mapping_history' => 'encrypted:array',
            'original_filename' => 'encrypted', 'file_path' => 'encrypted',
            'approved_at' => 'immutable_datetime', 'processing_started_at' => 'immutable_datetime',
            'processing_finished_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'file_purged_at' => 'immutable_datetime',
        ];
    }
}
