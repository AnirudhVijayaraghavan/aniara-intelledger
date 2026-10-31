<?php

namespace App\Models;

use App\Enums\TransactionKind;
use App\Enums\TransactionRevisionOperation;
use App\Enums\TransactionStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Database\Factories\AccountTransactionFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $team_id
 * @property int $financial_account_id
 * @property string $currency_code
 * @property string $amount
 * @property TransactionKind $kind
 * @property TransactionStatus $status
 * @property CarbonImmutable $transaction_date
 * @property CarbonImmutable|null $posted_date
 * @property CarbonImmutable|null $recorded_at
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $payee_id
 * @property int|null $reverses_transaction_id
 * @property int|null $replaces_transaction_id
 * @property bool $excluded_from_analytics
 * @property int|null $created_by
 * @property-read Team|null $team
 * @property-read FinancialAccount $account
 * @property-read TransactionCategory|null $category
 * @property-read Payee|null $payee
 */
#[Fillable(['team_id', 'financial_account_id', 'currency_code', 'amount', 'kind', 'status', 'transaction_date', 'posted_date', 'recorded_at', 'description', 'category_id', 'payee_id', 'reverses_transaction_id', 'replaces_transaction_id', 'excluded_from_analytics', 'created_by'])]
class AccountTransaction extends Model
{
    /** @use HasFactory<AccountTransactionFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['kind' => 'unclassified', 'status' => 'pending', 'excluded_from_analytics' => false];

    private ?int $revisionActorId = null;

    private ?string $revisionReason = null;

    public function withRevisionContext(?User $actor, ?string $reason = null): static
    {
        $this->revisionActorId = $actor?->id;
        $this->revisionReason = $reason;

        return $this;
    }

    /**
     * Ordinary model saves and canonical revisions commit together.
     * Query-builder bulk writes bypass this boundary and must not be used for financial history.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            $creating = ! $this->exists;
            Team::query()->whereKey($this->team_id)->lockForUpdate()->firstOrFail();
            $account = FinancialAccount::query()->where('team_id', $this->team_id)
                ->whereKey($this->financial_account_id)->lockForUpdate()->firstOrFail();

            if (! $creating) {
                $stored = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                foreach (array_keys($this->getRawOriginal()) as $field) {
                    $original = $this->getOriginal($field);
                    $current = $stored->getAttribute($field);
                    $hasChanged = $original instanceof DateTimeInterface && $current instanceof DateTimeInterface
                        ? $original->format('U.u') !== $current->format('U.u')
                        : $original !== $current;
                    if ($hasChanged) {
                        throw new DomainException('Reload the transaction before saving concurrent changes.');
                    }
                }
                if ($this->isDirty(['id', 'team_id', 'financial_account_id', 'currency_code', 'recorded_at', 'created_by', 'reverses_transaction_id', 'replaces_transaction_id'])) {
                    throw new DomainException('Transaction identity, currency, provenance and correction links are immutable.');
                }
                if ($stored->status !== TransactionStatus::Pending && $this->isDirty(['amount', 'transaction_date', 'posted_date', 'status'])) {
                    throw new DomainException('Posted or cancelled movements cannot be overwritten; record a correction.');
                }
                if (! $this->isDirty()) {
                    return true;
                }
                if ($this->isTransferLeg()) {
                    throw new DomainException('Transfer legs cannot be independently modified.');
                }
                if ($stored->isInReversalPair()) {
                    throw new DomainException('Reversed transactions and their reversals cannot be independently modified.');
                }
            }

            if ($account->archived_at !== null) {
                throw new DomainException('Archived accounts do not accept transaction changes.');
            }
            if ($account->currency_code !== $this->currency_code) {
                throw new DomainException('Transactions must use the account currency.');
            }
            if ($this->status === TransactionStatus::Posted && $this->posted_date === null) {
                throw new DomainException('Posted transactions require a posting date.');
            }
            if ($this->category_id !== null) {
                $category = TransactionCategory::query()->where('team_id', $this->team_id)->whereKey($this->category_id)->firstOrFail();
                if ($category->kind->value !== $this->kind->value) {
                    throw new DomainException('Transaction and category classifications must match.');
                }
            }

            $this->recorded_at ??= CarbonImmutable::now('UTC');
            if (! parent::save($options)) {
                return false;
            }

            $revision = $this->revisions()->create([
                'team_id' => $this->team_id,
                'revision_number' => ((int) $this->revisions()->reorder()->orderByDesc('revision_number')->lockForUpdate()->value('revision_number')) + 1,
                'operation' => $creating ? TransactionRevisionOperation::Created : TransactionRevisionOperation::Updated,
                'snapshot' => $this->canonicalSnapshot(),
                'actor_id' => $this->revisionActorId ?? ($creating ? $this->created_by : null),
                'reason' => $this->revisionReason,
                'recorded_at' => CarbonImmutable::now('UTC'),
            ]);
            if (! $revision->exists) {
                throw new DomainException('The transaction revision could not be recorded.');
            }
            $this->unsetRelation('revisions');

            return true;
        });
    }

    public function delete(): never
    {
        throw new DomainException('Transaction history cannot be physically deleted.');
    }

    /** @return array<string, int|string|bool|null> */
    public function canonicalSnapshot(): array
    {
        $recordedAt = $this->recorded_at?->utc()->toISOString();
        if ($recordedAt === null) {
            throw new DomainException('Transaction snapshots require a valid recording timestamp.');
        }

        return [
            'schema_version' => 1,
            'id' => $this->id,
            'team_id' => $this->team_id,
            'financial_account_id' => $this->financial_account_id,
            'currency_code' => $this->currency_code,
            'amount' => $this->amount,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'transaction_date' => $this->transaction_date->toDateString(),
            'posted_date' => $this->posted_date?->toDateString(),
            'recorded_at' => $recordedAt,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'payee_id' => $this->payee_id,
            'reverses_transaction_id' => $this->reverses_transaction_id,
            'replaces_transaction_id' => $this->replaces_transaction_id,
            'excluded_from_analytics' => $this->excluded_from_analytics,
            'created_by' => $this->created_by,
        ];
    }

    /** @return Attribute<never, string> */
    protected function amount(): Attribute
    {
        return Attribute::make(set: function (mixed $value): string {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Transaction amounts must be decimal strings.');
            }
            $amount = BigDecimal::of($value)->toScale(8, RoundingMode::Unnecessary);
            if ($amount->abs()->isGreaterThan('9999999999999999.99999999')) {
                throw new InvalidArgumentException('Transaction amount exceeds DECIMAL(24,8).');
            }

            return (string) $amount;
        });
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function recordedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn (DateTimeInterface|string|null $value): ?string => $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u'),
        );
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

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<TransactionCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'category_id');
    }

    /** @return BelongsTo<Payee, $this> */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function reversesTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    /** @return HasOne<AccountTransaction, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function isInReversalPair(): bool
    {
        return $this->reverses_transaction_id !== null || $this->reversal()->exists();
    }

    /** @return HasOne<AccountTransaction, $this> */
    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_transaction_id');
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function replacesTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_transaction_id');
    }

    /** @return HasMany<TransactionRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(TransactionRevision::class)->orderBy('revision_number');
    }

    /** @return HasMany<TransactionImportRow, $this> */
    public function importRows(): HasMany
    {
        return $this->hasMany(TransactionImportRow::class);
    }

    /** @return HasMany<TransactionSourceReference, $this> */
    public function sourceReferences(): HasMany
    {
        return $this->hasMany(TransactionSourceReference::class);
    }

    /** @return HasOne<TransactionTransfer, $this> */
    public function sourceTransfer(): HasOne
    {
        return $this->hasOne(TransactionTransfer::class, 'source_transaction_id');
    }

    /** @return HasOne<TransactionTransfer, $this> */
    public function destinationTransfer(): HasOne
    {
        return $this->hasOne(TransactionTransfer::class, 'destination_transaction_id');
    }

    public function isTransferLeg(): bool
    {
        return $this->exists && TransactionTransfer::query()->where('team_id', $this->team_id)
            ->where(fn ($query) => $query->where('source_transaction_id', $this->id)->orWhere('destination_transaction_id', $this->id))
            ->lockForUpdate()->value('id') !== null;
    }

    /** @param Builder<AccountTransaction> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'team_id' => 'integer', 'financial_account_id' => 'integer', 'category_id' => 'integer', 'payee_id' => 'integer',
            'reverses_transaction_id' => 'integer', 'replaces_transaction_id' => 'integer', 'created_by' => 'integer',
            'amount' => 'decimal:8', 'kind' => TransactionKind::class, 'status' => TransactionStatus::class,
            'transaction_date' => 'immutable_date', 'posted_date' => 'immutable_date',
            'recorded_at' => 'immutable_datetime', 'excluded_from_analytics' => 'boolean',
        ];
    }
}
