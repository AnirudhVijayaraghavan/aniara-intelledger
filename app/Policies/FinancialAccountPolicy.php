<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;

class FinancialAccountPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $this->canReadFinancialTeam($user, $account->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }

    public function update(User $user, FinancialAccount $account): bool
    {
        return $account->archived_at === null && $this->canManageFinancialTeam($user, $account->team);
    }

    public function archive(User $user, FinancialAccount $account): bool
    {
        return $this->canManageFinancialTeam($user, $account->team);
    }

    public function delete(User $user, FinancialAccount $account): bool
    {
        return false;
    }
}
