<?php

namespace App\Concerns;

use App\Enums\TransactionCategoryKind;
use App\Models\Team;
use App\Models\TransactionCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait TransactionCategoryValidationRules
{
    /** @return array<string, array<mixed>> */
    protected function categoryRules(Team $team, ?TransactionCategory $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', Rule::enum(TransactionCategoryKind::class)],
            'parent_id' => [
                'bail', 'nullable', 'integer', Rule::notIn([$category?->id]),
                Rule::exists('transaction_categories', 'id')->where('team_id', $team->id)->whereNull('parent_id')
                    ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $category?->parent_id)),
            ],
            'team_id' => ['prohibited'], 'id' => ['prohibited'], 'archived_at' => ['prohibited'],
        ];
    }

    protected function configureCategoryValidator(Validator $validator, Team $team, ?TransactionCategory $category = null): void
    {
        $validator->after(function (Validator $validator) use ($team, $category): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $input = $validator->getData();
            if ($category !== null && $input['kind'] !== $category->kind->value
                && ($category->transactions()->lockForUpdate()->value('id') !== null || $category->children()->lockForUpdate()->value('id') !== null)) {
                $validator->errors()->add('kind', 'The kind cannot change while transactions or subcategories reference this category.');
            }
            if (! empty($input['parent_id'])) {
                $parent = TransactionCategory::forTeam($team)->whereKey($input['parent_id'])->lockForUpdate()->firstOrFail();
                if ($parent->parent_id !== null || $parent->kind->value !== $input['kind']) {
                    $validator->errors()->add('parent_id', 'The parent must be a root category with the same income or expense kind.');
                }
                if ($parent->getAttribute('archived_at') !== null && $parent->id !== $category?->parent_id) {
                    $validator->errors()->add('parent_id', 'Archived categories cannot receive new subcategories.');
                }
                if ($category !== null && $category->children()->lockForUpdate()->value('id') !== null) {
                    $validator->errors()->add('parent_id', 'A category with subcategories must remain a root category.');
                }
            }
        });
    }
}
