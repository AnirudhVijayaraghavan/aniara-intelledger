<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\Payee;
use App\Models\Team;
use App\Models\User;

class PayeePolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, Payee $payee): bool
    {
        return $this->canReadFinancialTeam($user, $payee->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }

    public function update(User $user, Payee $payee): bool
    {
        return $this->canManageFinancialTeam($user, $payee->team);
    }

    public function archive(User $user, Payee $payee): bool
    {
        return $this->canManageFinancialTeam($user, $payee->team);
    }

    public function delete(User $user, Payee $payee): bool
    {
        return false;
    }
}
