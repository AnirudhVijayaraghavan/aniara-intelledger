<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property int $minor_units
 */
#[Fillable(['code', 'name', 'minor_units'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @return HasMany<FinancialAccount, $this> */
    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class, 'currency_code', 'code');
    }

    /** @return HasMany<ExchangeRate, $this> */
    public function baseExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency_code', 'code');
    }

    /** @return HasMany<ExchangeRate, $this> */
    public function quotedExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'quote_currency_code', 'code');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['minor_units' => 'integer'];
    }
}
