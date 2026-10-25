<?php

namespace App\Models;

use Database\Factories\FinancialInstitutionFactory;
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
 * @property string $name
 * @property string|null $country_code
 * @property Carbon|null $archived_at
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'name', 'country_code', 'archived_at'])]
class FinancialInstitution extends Model
{
    /** @use HasFactory<FinancialInstitutionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (FinancialInstitution $institution): void {
            if ($institution->isDirty('team_id')) {
                throw new DomainException('Financial institutions cannot be moved between teams.');
            }
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<FinancialAccount, $this> */
    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    /** @param Builder<FinancialInstitution> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
