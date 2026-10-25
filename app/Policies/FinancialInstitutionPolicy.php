<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\User;

class FinancialInstitutionPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, FinancialInstitution $institution): bool
    {
        return $this->canReadFinancialTeam($user, $institution->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }

    public function update(User $user, FinancialInstitution $institution): bool
    {
        return $this->canManageFinancialTeam($user, $institution->team);
    }

    public function archive(User $user, FinancialInstitution $institution): bool
    {
        return $this->canManageFinancialTeam($user, $institution->team);
    }

    public function delete(User $user, FinancialInstitution $institution): bool
    {
        return false;
    }
}
