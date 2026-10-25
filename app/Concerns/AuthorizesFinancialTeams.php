<?php

namespace App\Concerns;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

trait AuthorizesFinancialTeams
{
    protected function canReadFinancialTeam(User $user, ?Team $team): bool
    {
        return $team !== null && ! $team->trashed() && $user->belongsToTeam($team);
    }

    protected function canManageFinancialTeam(User $user, ?Team $team): bool
    {
        return $team !== null && $this->canReadFinancialTeam($user, $team)
            && in_array($user->teamRole($team), [TeamRole::Owner, TeamRole::Admin], true);
    }
}
