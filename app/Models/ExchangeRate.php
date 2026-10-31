<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Database\Factories\ExchangeRateFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * One unit of base currency equals rate units of quote currency.
 *
 * @property int $id
 * @property int $team_id
 * @property string $base_currency_code
 * @property string $quote_currency_code
 * @property string $rate
 * @property string $source
 * @property CarbonImmutable $effective_at
 * @property CarbonImmutable $recorded_at
 * @property int|null $created_by
 */
#[Fillable(['team_id', 'base_currency_code', 'quote_currency_code', 'rate', 'effective_at', 'source', 'recorded_at', 'created_by'])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['source' => 'manual'];

    protected static function booted(): void
    {
        static::creating(function (ExchangeRate $rate): void {
            $rate->base_currency_code = strtoupper($rate->base_currency_code);
            $rate->quote_currency_code = strtoupper($rate->quote_currency_code);
            $rate->recorded_at ??= now()->utc();
            if ($rate->base_currency_code === $rate->quote_currency_code) {
                throw new DomainException('Identity conversions do not require stored exchange rates.');
            }
        });
        static::updating(function (): never {
            throw new DomainException('Exchange rates are immutable; append a new observation.');
        });
        static::deleting(function (): never {
            throw new DomainException('Historical exchange rates cannot be deleted.');
        });
    }

    public static function normalizeRate(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 80 || ! preg_match('/\\A-?[0-9]+(?:\\.[0-9]+)?\\z/', $value)) {
            throw new InvalidArgumentException('Exchange rates must be plain decimal strings.');
        }

        $rate = BigDecimal::of($value)->toScale(18, RoundingMode::Unnecessary);
        if (! $rate->isPositive() || $rate->isGreaterThan('999999999999999999.999999999999999999')) {
            throw new InvalidArgumentException('Exchange rates must be positive and fit DECIMAL(36,18).');
        }

        return (string) $rate;
    }

    /** @return Attribute<never, string> */
    protected function rate(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): string => self::normalizeRate($value));
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function effectiveAt(): Attribute
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

    /** @return BelongsTo<Currency, $this> */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_code', 'code');
    }

    /** @return BelongsTo<Currency, $this> */
    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<ExchangeRate> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rate' => 'decimal:18'];
    }
}
