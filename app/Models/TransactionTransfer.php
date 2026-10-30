<?php

namespace App\Models;

use App\Enums\TransactionKind;
use App\Enums\TransactionRevisionOperation;
use App\Enums\TransactionStatus;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionTransferFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One unit of FX base currency equals fx_rate units of FX quote currency.
 * Native booked amounts live only on the immutable transaction legs.
 *
 * @property int $id
 * @property int $team_id
 * @property int $source_account_id
 * @property int $destination_account_id
 * @property int $source_transaction_id
 * @property int $destination_transaction_id
 * @property string|null $fx_base_currency_code
 * @property string|null $fx_quote_currency_code
 * @property string|null $fx_rate
 * @property string|null $fx_source
 * @property CarbonImmutable|null $fx_effective_at
 * @property CarbonImmutable $recorded_at
 * @property int|null $created_by
 * @property int|null $reverses_transfer_id
 * @property-read Team|null $team
 * @property-read FinancialAccount $sourceAccount
 * @property-read FinancialAccount $destinationAccount
 * @property-read AccountTransaction $sourceTransaction
 * @property-read AccountTransaction $destinationTransaction
 */
#[Fillable(['team_id', 'source_account_id', 'destination_account_id', 'source_transaction_id', 'destination_transaction_id', 'fx_base_currency_code', 'fx_quote_currency_code', 'fx_rate', 'fx_source', 'fx_effective_at', 'recorded_at', 'created_by', 'reverses_transfer_id'])]
class TransactionTransfer extends Model
{
    /** @use HasFactory<TransactionTransferFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['reverses_transfer_id' => null];

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new DomainException('Completed transfers are immutable; use the transfer reversal workflow.');
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $team = Team::query()->whereKey($this->team_id)->lockForUpdate()->firstOrFail();
            if ($this->source_account_id === $this->destination_account_id) {
                throw new DomainException('A transfer requires different accounts.');
            }
            $accounts = $team->financialAccounts()->whereKey([$this->source_account_id, $this->destination_account_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sourceAccount = $accounts->get($this->source_account_id);
            $destinationAccount = $accounts->get($this->destination_account_id);
            if ($sourceAccount === null || $destinationAccount === null || $sourceAccount->archived_at !== null || $destinationAccount->archived_at !== null) {
                throw new DomainException('Transfers require two active accounts in the household.');
            }
            $source = AccountTransaction::forTeam($team)->whereKey($this->source_transaction_id)->lockForUpdate()->firstOrFail();
            $destination = AccountTransaction::forTeam($team)->whereKey($this->destination_transaction_id)->lockForUpdate()->firstOrFail();
            if ($source->financial_account_id !== $this->source_account_id || $destination->financial_account_id !== $this->destination_account_id) {
                throw new DomainException('Transfer legs must match their recorded accounts.');
            }
            if (static::query()->forTeam($team)->where(fn ($query) => $query
                ->whereIn('source_transaction_id', [$source->id, $destination->id])
                ->orWhereIn('destination_transaction_id', [$source->id, $destination->id]))->lockForUpdate()->value('id') !== null) {
                throw new DomainException('A transaction can belong to only one transfer.');
            }
            if ($this->reverses_transfer_id === null) {
                foreach ([$source, $destination] as $leg) {
                    $this->validateLeg($leg);
                }
            } else {
                $this->validateReversal($team, $source, $destination);
            }

            $sourceEffect = BigDecimal::of($sourceAccount->type->netWorthContribution($source->amount));
            $destinationEffect = BigDecimal::of($destinationAccount->type->netWorthContribution($destination->amount));
            if (! $sourceEffect->isNegative() || ! $destinationEffect->isPositive()) {
                throw new DomainException('The source must have a negative economic effect and the destination a positive effect.');
            }
            if ($source->currency_code === $destination->currency_code && ! $sourceEffect->plus($destinationEffect)->isZero()) {
                throw new DomainException('Same-currency transfers must have offsetting economic effects.');
            }
            $this->validateExecutionFx($source->currency_code, $destination->currency_code);
            $this->recorded_at ??= CarbonImmutable::now('UTC');

            return parent::save($options);
        });
    }

    private function validateLeg(AccountTransaction $leg, ?int $reversesTransactionId = null, bool $historical = false): void
    {
        if ($leg->kind !== TransactionKind::Transfer || $leg->status !== TransactionStatus::Posted || ! $leg->excluded_from_analytics
            || $leg->category_id !== null || $leg->payee_id !== null || $leg->reverses_transaction_id !== $reversesTransactionId || $leg->replaces_transaction_id !== null) {
            throw new DomainException('Transfer legs must be posted, uncategorized transfer movements excluded from analytics.');
        }
        $revisions = $leg->revisions()->lockForUpdate()->limit(2)->get();
        $revision = $revisions->first();
        if ($revisions->count() !== 1 || $revision?->revision_number !== 1 || $revision->operation !== TransactionRevisionOperation::Created) {
            throw new DomainException('Each new transfer leg must have exactly one creation revision.');
        }
        $snapshot = $revision->snapshot;
        $expected = $leg->canonicalSnapshot();
        if ($historical && $expected['created_by'] === null && ($snapshot['created_by'] ?? null) !== null
            && ! User::query()->whereKey($snapshot['created_by'])->exists()) {
            $expected['created_by'] = $snapshot['created_by'];
        }
        ksort($snapshot);
        ksort($expected);
        if ($snapshot !== $expected) {
            throw new DomainException('The transfer leg audit must match its accepted state.');
        }
    }

    private function validateReversal(Team $team, AccountTransaction $source, AccountTransaction $destination): void
    {
        $original = $team->transactionTransfers()->whereKey($this->reverses_transfer_id)->lockForUpdate()->firstOrFail();
        if ($original->reverses_transfer_id !== null || $original->reversal()->exists()) {
            throw new DomainException('A transfer can be reversed only once and reversal transfers cannot be reversed.');
        }
        if ($this->source_account_id !== $original->destination_account_id || $this->destination_account_id !== $original->source_account_id) {
            throw new DomainException('A reversal transfer must reverse the original account direction.');
        }
        $originalLegs = AccountTransaction::forTeam($team)->whereKey([$original->source_transaction_id, $original->destination_transaction_id])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ([[$source, $original->destination_transaction_id], [$destination, $original->source_transaction_id]] as [$leg, $originalId]) {
            $originalLeg = $originalLegs->get($originalId);
            if ($originalLeg === null) {
                throw new DomainException('The original transfer leg is missing.');
            }
            $this->validateLeg($originalLeg, historical: true);
            $this->validateLeg($leg, $originalLeg->id);
            if ($leg->currency_code !== $originalLeg->currency_code || ! BigDecimal::of($leg->amount)->isEqualTo(BigDecimal::of($originalLeg->amount)->negated())
                || $leg->posted_date?->lessThan($originalLeg->posted_date)) {
                throw new DomainException('Reversal legs must negate original booked movements on or after their posting dates.');
            }
        }
        foreach (['fx_rate', 'fx_base_currency_code', 'fx_quote_currency_code', 'fx_source', 'fx_effective_at'] as $field) {
            if ($this->getAttribute($field) !== null) {
                throw new DomainException('Historical FX belongs to the original transfer, not a new execution quote.');
            }
        }
    }

    private function validateExecutionFx(string $sourceCurrency, string $destinationCurrency): void
    {
        $fields = ['fx_base_currency_code', 'fx_quote_currency_code', 'fx_rate', 'fx_source', 'fx_effective_at'];
        $present = count(array_filter($fields, fn (string $field): bool => $this->getAttribute($field) !== null));
        if ($present === 0) {
            return;
        }
        if ($present !== 5 || trim($this->fx_source ?? '') === '' || $sourceCurrency === $destinationCurrency
            || ! (($this->fx_base_currency_code === $sourceCurrency && $this->fx_quote_currency_code === $destinationCurrency)
                || ($this->fx_base_currency_code === $destinationCurrency && $this->fx_quote_currency_code === $sourceCurrency))) {
            throw new DomainException('Execution FX provenance must be complete and match the two account currencies.');
        }
    }

    public function delete(): never
    {
        throw new DomainException('Transfer history cannot be deleted.');
    }

    /** @return Attribute<string|null, string|null> */
    protected function fxRate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : ExchangeRate::normalizeRate($value),
            set: fn (mixed $value): ?string => $value === null ? null : ExchangeRate::normalizeRate($value),
        );
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function fxEffectiveAt(): Attribute
    {
        return $this->utcTimestamp();
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function recordedAt(): Attribute
    {
        return $this->utcTimestamp();
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    private function utcTimestamp(): Attribute
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
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'source_account_id');
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'destination_account_id');
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'source_transaction_id');
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function destinationTransaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'destination_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<TransactionTransfer, $this> */
    public function reversesTransfer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transfer_id');
    }

    /** @return HasOne<TransactionTransfer, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transfer_id');
    }

    /** @param Builder<TransactionTransfer> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'team_id' => 'integer', 'source_account_id' => 'integer', 'destination_account_id' => 'integer',
            'source_transaction_id' => 'integer', 'destination_transaction_id' => 'integer', 'created_by' => 'integer',
            'reverses_transfer_id' => 'integer',
            'fx_rate' => 'decimal:18', 'fx_effective_at' => 'immutable_datetime', 'recorded_at' => 'immutable_datetime',
        ];
    }
}
