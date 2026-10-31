<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\Team;
use App\Models\TransactionTransfer;
use App\Models\User;

class TransactionTransferPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, TransactionTransfer $transfer): bool
    {
        return $this->canReadFinancialTeam($user, $transfer->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }

    public function update(User $user, TransactionTransfer $transfer): bool
    {
        return false;
    }

    public function reverse(User $user, TransactionTransfer $transfer): bool
    {
        return $this->canManageFinancialTeam($user, $transfer->team);
    }

    public function delete(User $user, TransactionTransfer $transfer): bool
    {
        return false;
    }
}
