<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\Team;
use App\Models\User;

class ExchangeRatePolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }
}
