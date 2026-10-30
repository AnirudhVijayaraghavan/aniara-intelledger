<?php

namespace App\Models;

use App\Enums\TransactionRevisionOperation;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionRevisionFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $account_transaction_id
 * @property int $revision_number
 * @property TransactionRevisionOperation $operation
 * @property array<string, int|string|bool|null> $snapshot
 * @property int|null $actor_id
 * @property CarbonImmutable $recorded_at
 * @property-read AccountTransaction $transaction
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'account_transaction_id', 'revision_number', 'operation', 'snapshot', 'reason', 'actor_id', 'recorded_at'])]
class TransactionRevision extends Model
{
    /** @use HasFactory<TransactionRevisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new DomainException('Transaction revisions are append-only.');
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $transaction = AccountTransaction::query()->where('team_id', $this->team_id)
                ->whereKey($this->account_transaction_id)->lockForUpdate()->firstOrFail();
            $number = ((int) $transaction->revisions()->reorder()->orderByDesc('revision_number')->lockForUpdate()->value('revision_number')) + 1;
            $operation = $number === 1 ? TransactionRevisionOperation::Created : TransactionRevisionOperation::Updated;
            if ($this->revision_number !== $number || $this->operation !== $operation || $this->snapshot !== $transaction->canonicalSnapshot()) {
                throw new DomainException('Revisions require the next sequence and the canonical transaction snapshot.');
            }
            $this->recorded_at ??= CarbonImmutable::now('UTC');

            return parent::save($options);
        });
    }

    public function delete(): never
    {
        throw new DomainException('Transaction revisions cannot be deleted.');
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function recordedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn (DateTimeInterface|string|null $value): ?string => $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u'),
        );
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'account_transaction_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @param Builder<TransactionRevision> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['team_id' => 'integer', 'account_transaction_id' => 'integer', 'actor_id' => 'integer', 'revision_number' => 'integer', 'operation' => TransactionRevisionOperation::class, 'snapshot' => 'array', 'recorded_at' => 'immutable_datetime'];
    }
}
