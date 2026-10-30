<?php

namespace App\Models;

use Database\Factories\PayeeFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'name', 'archived_at'])]
class Payee extends Model
{
    /** @use HasFactory<PayeeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Payee $payee): void {
            if ($payee->isDirty('team_id')) {
                throw new DomainException('Payees cannot move between teams.');
            }
        });
        static::deleting(function (): never {
            throw new DomainException('Archive payees instead of deleting them.');
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<AccountTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    /** @param Builder<Payee> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }
}
