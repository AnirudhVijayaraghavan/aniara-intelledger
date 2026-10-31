<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\User;

class TransactionRevisionPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, TransactionRevision $revision): bool
    {
        return $this->canReadFinancialTeam($user, $revision->team);
    }

    public function create(User $user, Team $team): bool
    {
        return false;
    }

    public function update(User $user, TransactionRevision $revision): bool
    {
        return false;
    }

    public function delete(User $user, TransactionRevision $revision): bool
    {
        return false;
    }
}
