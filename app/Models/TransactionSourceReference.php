<?php

namespace App\Models;

use Database\Factories\TransactionSourceReferenceFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $financial_account_id
 * @property int $account_transaction_id
 * @property-read Team|null $team
 * @property-read FinancialAccount $account
 */
#[Fillable(['team_id', 'financial_account_id', 'account_transaction_id', 'transaction_import_row_id', 'source_namespace', 'source_key_hash', 'identity_version', 'recorded_by', 'recorded_at'])]
class TransactionSourceReference extends Model
{
    /** @use HasFactory<TransactionSourceReferenceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['identity_version' => 1];

    protected $hidden = ['source_key_hash'];

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new DomainException('Transaction source references are append-only.');
        }
        if (preg_match('/\A[a-f0-9]{64}\z/', $this->source_key_hash) !== 1
            || preg_match('/\A[a-z0-9][a-z0-9_.:-]{0,63}\z/', $this->source_namespace) !== 1) {
            throw new DomainException('Source references require a canonical namespace and SHA-256 identity.');
        }
        $this->recorded_at ??= now()->utc();

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new DomainException('Transaction source references cannot be deleted.');
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

    /** @return BelongsTo<AccountTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'account_transaction_id');
    }

    /** @return BelongsTo<TransactionImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(TransactionImportRow::class, 'transaction_import_row_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @param Builder<TransactionSourceReference> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['team_id' => 'integer', 'financial_account_id' => 'integer', 'account_transaction_id' => 'integer',
            'transaction_import_row_id' => 'integer', 'identity_version' => 'integer', 'recorded_at' => 'immutable_datetime'];
    }
}
