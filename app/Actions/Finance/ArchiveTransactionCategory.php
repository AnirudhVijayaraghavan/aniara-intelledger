<?php

namespace App\Actions\Finance;

use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ArchiveTransactionCategory
{
    public function handle(User $actor, Team $team, TransactionCategory $category): TransactionCategory
    {
        return DB::transaction(function () use ($actor, $team, $category): TransactionCategory {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $category = $team->transactionCategories()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $category->setRelation('team', $team);
            Gate::forUser($actor)->authorize('archive', $category);
            if ($category->children()->whereNull('archived_at')->lockForUpdate()->value('id') !== null) {
                throw ValidationException::withMessages(['archive' => 'Archive the active subcategories before archiving their parent.']);
            }
            if ($category->getAttribute('archived_at') === null) {
                $category->archived_at = now()->utc();
                $category->saveOrFail();
            }

            return $category;
        }, attempts: 3);
    }
}
