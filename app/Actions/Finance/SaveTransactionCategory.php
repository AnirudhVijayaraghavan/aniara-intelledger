<?php

namespace App\Actions\Finance;

use App\Concerns\TransactionCategoryValidationRules;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SaveTransactionCategory
{
    use TransactionCategoryValidationRules;

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, array $input, ?TransactionCategory $category = null): TransactionCategory
    {
        return DB::transaction(function () use ($actor, $team, $input, $category): TransactionCategory {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            if ($category !== null) {
                $category = $team->transactionCategories()->whereKey($category->id)->lockForUpdate()->firstOrFail();
                $category->setRelation('team', $team);
                Gate::forUser($actor)->authorize('update', $category);
            } else {
                Gate::forUser($actor)->authorize('create', [TransactionCategory::class, $team]);
            }
            $validator = Validator::make($input, $this->categoryRules($team, $category));
            $this->configureCategoryValidator($validator, $team, $category);
            $validated = $validator->validate();
            $category ??= $team->transactionCategories()->make();
            $category->fill(['name' => $validated['name'], 'kind' => $validated['kind'], 'parent_id' => $validated['parent_id'] ?? null]);
            $category->saveOrFail();

            return $category;
        }, attempts: 3);
    }
}
