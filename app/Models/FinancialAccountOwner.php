<?php

namespace App\Models;

use Database\Factories\FinancialAccountOwnerFactory;
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
 * @property string $owner_name
 * @property int|null $user_id
 * @property-read Team|null $team
 * @property-read FinancialAccount $account
 * @property-read User|null $user
 */
#[Fillable(['team_id', 'financial_account_id', 'owner_name', 'user_id'])]
class FinancialAccountOwner extends Model
{
    /** @use HasFactory<FinancialAccountOwnerFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (FinancialAccountOwner $owner): void {
            if ($owner->isDirty(['team_id', 'financial_account_id'])) {
                throw new DomainException('Recorded holders cannot be moved between accounts or teams.');
            }
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<FinancialAccountOwner> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }
}
