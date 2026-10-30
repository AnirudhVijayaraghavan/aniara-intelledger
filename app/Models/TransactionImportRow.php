<?php

namespace App\Models;

use App\Enums\TransactionImportDisposition;
use App\Enums\TransactionImportRowStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\TransactionImportRowFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $team_id
 * @property int $financial_account_id
 * @property int $transaction_import_batch_id
 * @property int $mapping_version
 * @property int|null $account_transaction_id
 * @property TransactionImportRowStatus $status
 * @property TransactionImportDisposition|null $disposition
 * @property array<string, mixed>|null $normalized_data
 * @property list<int>|null $duplicate_candidates
 * @property-read Team|null $team
 * @property-read FinancialAccount $account
 * @property-read TransactionImportBatch $batch
 */
#[Fillable(['team_id', 'financial_account_id', 'transaction_import_batch_id', 'record_number', 'mapping_version', 'original_fields', 'normalized_data', 'validation_errors', 'validation_warnings', 'duplicate_candidates', 'candidate_fingerprint', 'status', 'disposition', 'account_transaction_id', 'applied_revision_number', 'expected_revision', 'decided_by', 'decided_at', 'decision_reason', 'attempts', 'last_error_code', 'processed_at'])]
class TransactionImportRow extends Model
{
    /** @use HasFactory<TransactionImportRowFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['status' => 'pending', 'mapping_version' => 1, 'attempts' => 0];

    protected $hidden = ['original_fields', 'normalized_data', 'validation_errors', 'validation_warnings', 'duplicate_candidates', 'candidate_fingerprint', 'decision_reason'];

    public function setAttribute(mixed $key, mixed $value): static
    {
        if ($key === 'normalized_data' && $value !== null) {
            if (! is_array($value)) {
                throw new InvalidArgumentException('Normalized financial data must be an array.');
            }
            $this->rejectFloats($value);
        }
        if ($key === 'duplicate_candidates' && $value !== null) {
            if (! is_array($value) || ! array_is_list($value)) {
                throw new DomainException('Duplicate candidates must be a list of transaction IDs.');
            }
            foreach ($value as $id) {
                if (! is_int($id) || $id < 1) {
                    throw new DomainException('Duplicate candidates must be transaction IDs.');
                }
            }
        }
        parent::setAttribute($key, $value);

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            $team = Team::whereKey($this->team_id)->lockForUpdate()->firstOrFail();
            $account = $team->financialAccounts()->whereKey($this->financial_account_id)->lockForUpdate()->firstOrFail();
            $batch = TransactionImportBatch::forTeam($team)->where('financial_account_id', $account->id)
                ->whereKey($this->transaction_import_batch_id)->lockForUpdate()->firstOrFail();
            if ($this->exists) {
                $stored = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
                if ($stored->isCommitted() && $this->isDirty()) {
                    throw new DomainException('Committed import rows are immutable.');
                }
                if ($stored->isCommitted()) {
                    return true;
                }
                if ($this->isDirty(['team_id', 'financial_account_id', 'transaction_import_batch_id', 'record_number', 'original_fields'])) {
                    throw new DomainException('Original import rows and their source identities are immutable.');
                }
                if ($stored->mapping_version !== $this->getOriginal('mapping_version') || $stored->status !== $this->getOriginal('status')) {
                    throw new DomainException('Reload the import row before changing it.');
                }
            }
            if ($this->mapping_version !== $batch->mapping_version) {
                throw new DomainException('Import rows must be validated against the current mapping version.');
            }
            $data = $this->normalized_data;
            if ($data !== null) {
                $this->rejectFloats($data);
                if (array_key_exists('amount', $data)) {
                    if (! is_string($data['amount'])) {
                        throw new InvalidArgumentException('Normalized amounts must be decimal strings.');
                    }
                    $amount = BigDecimal::of($data['amount'])->toScale(8, RoundingMode::Unnecessary);
                    if ($amount->abs()->isGreaterThan('9999999999999999.99999999')) {
                        throw new InvalidArgumentException('Normalized amount exceeds DECIMAL(24,8).');
                    }
                    $data['amount'] = (string) $amount;
                }
                if (isset($data['currency_code']) && $data['currency_code'] !== $account->currency_code) {
                    throw new DomainException('Normalized booked currency must match the account currency.');
                }
                if ($data !== $this->normalized_data) {
                    $this->normalized_data = $data;
                }
            }
            $candidates = $this->duplicate_candidates ?? [];
            if ($candidates !== [] && $account->transactions()->forTeam($team)->whereKey($candidates)->count() !== count(array_unique($candidates))) {
                throw new DomainException('Duplicate candidates must belong to the import account.');
            }

            return parent::save($options);
        });
    }

    public function isCommitted(): bool
    {
        return $this->account_transaction_id !== null;
    }

    /** @param array<array-key, mixed> $values */
    private function rejectFloats(array $values): void
    {
        foreach ($values as $value) {
            if (is_float($value)) {
                throw new InvalidArgumentException('Normalized financial data cannot contain PHP floats.');
            }
            if (is_array($value)) {
                $this->rejectFloats($value);
            }
        }
    }

    public function delete(): never
    {
        throw new DomainException('Import rows cannot be deleted.');
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

    /** @return BelongsTo<TransactionImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(TransactionImportBatch::class, 'transaction_import_batch_id');
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'account_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<TransactionSourceReference, $this> */
    public function sourceReferences(): HasMany
    {
        return $this->hasMany(TransactionSourceReference::class);
    }

    /** @param Builder<TransactionImportRow> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'team_id' => 'integer', 'financial_account_id' => 'integer', 'transaction_import_batch_id' => 'integer',
            'record_number' => 'integer', 'mapping_version' => 'integer', 'account_transaction_id' => 'integer',
            'applied_revision_number' => 'integer', 'expected_revision' => 'integer', 'attempts' => 'integer',
            'status' => TransactionImportRowStatus::class, 'disposition' => TransactionImportDisposition::class,
            'original_fields' => 'encrypted:array', 'normalized_data' => 'encrypted:array',
            'validation_errors' => 'encrypted:array', 'validation_warnings' => 'encrypted:array',
            'duplicate_candidates' => 'encrypted:array', 'decision_reason' => 'encrypted',
            'processed_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime',
        ];
    }
}
