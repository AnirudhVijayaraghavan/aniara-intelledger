<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\User;

class TransactionCategoryPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, TransactionCategory $category): bool
    {
        return $this->canReadFinancialTeam($user, $category->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }

    public function update(User $user, TransactionCategory $category): bool
    {
        return $this->canManageFinancialTeam($user, $category->team);
    }

    public function archive(User $user, TransactionCategory $category): bool
    {
        return $this->canManageFinancialTeam($user, $category->team);
    }

    public function delete(User $user, TransactionCategory $category): bool
    {
        return false;
    }
}
