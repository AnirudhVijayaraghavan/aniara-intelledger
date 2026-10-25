<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\User;

class FinancialAccountOwnerPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, FinancialAccount $account): bool
    {
        return $this->canReadFinancialTeam($user, $account->team);
    }

    public function view(User $user, FinancialAccountOwner $owner): bool
    {
        return $this->canReadFinancialTeam($user, $owner->team);
    }

    public function create(User $user, FinancialAccount $account): bool
    {
        return $this->canManageFinancialTeam($user, $account->team);
    }

    public function update(User $user, FinancialAccountOwner $owner): bool
    {
        return $this->canManageFinancialTeam($user, $owner->team);
    }

    public function delete(User $user, FinancialAccountOwner $owner): bool
    {
        return false;
    }
}
