<?php

namespace App\Models;

use App\Enums\AccountBalanceSource;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Database\Factories\AccountBalanceFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $team_id
 * @property int $financial_account_id
 * @property string $amount
 * @property CarbonImmutable $as_of_date
 * @property CarbonImmutable $recorded_at
 * @property AccountBalanceSource $source
 * @property int|null $created_by
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property-read Team|null $team
 * @property-read FinancialAccount $account
 */
#[Fillable(['team_id', 'financial_account_id', 'as_of_date', 'recorded_at', 'amount', 'source', 'created_by', 'voided_at', 'voided_by'])]
class AccountBalance extends Model
{
    /** @use HasFactory<AccountBalanceFactory> */
    use HasFactory;

    protected $attributes = ['source' => 'manual'];

    protected static function booted(): void
    {
        static::creating(function (AccountBalance $balance): void {
            $balance->recorded_at ??= now();
        });

        static::updating(function (AccountBalance $balance): void {
            if ($balance->isDirty(['team_id', 'financial_account_id', 'as_of_date', 'recorded_at', 'amount', 'source', 'created_by'])) {
                throw new DomainException('Balance observations are immutable; append a correction instead.');
            }

            if ($balance->getRawOriginal('voided_at') !== null && $balance->isDirty(['voided_at', 'voided_by'])) {
                throw new DomainException('Voided observations cannot be reinstated or reattributed.');
            }
        });

        static::deleting(function (): never {
            throw new DomainException('Balance history cannot be deleted; void the observation instead.');
        });
    }

    /** @return Attribute<never, DateTimeInterface|string|null> */
    protected function recordedAt(): Attribute
    {
        return Attribute::make(set: fn (DateTimeInterface|string|null $value): ?string => $value === null
            ? null
            : CarbonImmutable::parse($value)->format('Y-m-d H:i:s.u'));
    }

    /** @return Attribute<never, string> */
    protected function amount(): Attribute
    {
        return Attribute::make(set: function (mixed $value): string {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Balance amounts must be decimal strings.');
            }

            $decimal = BigDecimal::of($value)->toScale(8, RoundingMode::Unnecessary);

            if ($decimal->abs()->isGreaterThan('9999999999999999.99999999')) {
                throw new InvalidArgumentException('Balance amount exceeds DECIMAL(24,8).');
            }

            return (string) $decimal;
        });
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @param Builder<AccountBalance> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @param Builder<AccountBalance> $query */
    #[Scope]
    protected function notVoided(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /** @param Builder<AccountBalance> $query */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->orderByDesc('as_of_date')->orderByDesc('recorded_at')->orderByDesc('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'source' => AccountBalanceSource::class,
            'as_of_date' => 'immutable_date',
            'recorded_at' => 'immutable_datetime',
            'voided_at' => 'datetime',
        ];
    }
}
