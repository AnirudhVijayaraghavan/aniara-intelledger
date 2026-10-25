<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use Database\Factories\FinancialAccountFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $financial_institution_id
 * @property string $name
 * @property FinancialAccountType $type
 * @property string $currency_code
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property-read Team|null $team
 * @property-read Currency $currency
 * @property-read FinancialInstitution|null $institution
 */
#[Fillable(['team_id', 'financial_institution_id', 'name', 'type', 'currency_code', 'created_by', 'archived_at'])]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (FinancialAccount $account): void {
            if ($account->isDirty('team_id')) {
                throw new DomainException('Financial accounts cannot be moved between teams.');
            }

            if ($account->isDirty(['currency_code', 'type']) && $account->balances()->exists()) {
                $originalType = FinancialAccountType::from($account->getRawOriginal('type'));

                if ($account->isDirty('currency_code') || $originalType->isLiability() !== $account->type->isLiability()) {
                    throw new DomainException('Currency and asset/liability classification cannot change after balances have been recorded.');
                }
            }
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<FinancialInstitution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class, 'financial_institution_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AccountBalance, $this> */
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class);
    }

    /** @return HasMany<FinancialAccountOwner, $this> */
    public function owners(): HasMany
    {
        return $this->hasMany(FinancialAccountOwner::class);
    }

    /** @param Builder<FinancialAccount> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => FinancialAccountType::class, 'archived_at' => 'datetime'];
    }
}
