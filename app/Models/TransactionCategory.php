<?php

namespace App\Models;

use App\Enums\TransactionCategoryKind;
use Database\Factories\TransactionCategoryFactory;
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
 * @property TransactionCategoryKind $kind
 * @property int|null $parent_id
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'name', 'kind', 'parent_id', 'archived_at'])]
class TransactionCategory extends Model
{
    /** @use HasFactory<TransactionCategoryFactory> */
    use HasFactory;

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            Team::query()->whereKey($this->team_id)->lockForUpdate()->firstOrFail();
            if ($this->exists) {
                $stored = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                if ($stored->kind !== $this->getOriginal('kind') || $stored->parent_id !== $this->getOriginal('parent_id')) {
                    throw new DomainException('Reload the category before changing its hierarchy or classification.');
                }
            }

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::saving(function (TransactionCategory $category): void {
            if ($category->exists && $category->isDirty('team_id')) {
                throw new DomainException('Categories cannot move between teams.');
            }
            if ($category->exists && $category->isDirty('kind') && ($category->transactions()->lockForUpdate()->value('id') !== null || $category->children()->lockForUpdate()->value('id') !== null)) {
                throw new DomainException('A referenced category classification cannot change.');
            }
            if ($category->parent_id !== null) {
                $parent = self::query()->where('team_id', $category->team_id)->whereKey($category->parent_id)->lockForUpdate()->firstOrFail();
                if ($parent->id === $category->id || $parent->parent_id !== null || $parent->kind !== $category->kind || ($category->exists && $category->children()->lockForUpdate()->value('id') !== null)) {
                    throw new DomainException('Subcategories require a root parent with the same classification.');
                }
            }
        });
        static::deleting(function (): never {
            throw new DomainException('Archive categories instead of deleting them.');
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<TransactionCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<TransactionCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<AccountTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'category_id');
    }

    /** @param Builder<TransactionCategory> $query */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where($this->qualifyColumn('team_id'), $team->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['team_id' => 'integer', 'parent_id' => 'integer', 'kind' => TransactionCategoryKind::class, 'archived_at' => 'immutable_datetime'];
    }
}
