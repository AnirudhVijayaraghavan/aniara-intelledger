<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\User;

class AccountBalancePolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, FinancialAccount $account): bool
    {
        return $this->canReadFinancialTeam($user, $account->team);
    }

    public function view(User $user, AccountBalance $balance): bool
    {
        return $this->canReadFinancialTeam($user, $balance->team);
    }

    public function create(User $user, FinancialAccount $account): bool
    {
        return $account->archived_at === null && $this->canManageFinancialTeam($user, $account->team);
    }

    public function update(User $user, AccountBalance $balance): bool
    {
        return false;
    }

    public function voidObservation(User $user, AccountBalance $balance): bool
    {
        return $balance->voided_at === null && $this->canManageFinancialTeam($user, $balance->team);
    }

    public function delete(User $user, AccountBalance $balance): bool
    {
        return false;
    }
}
