<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionSourceReference;
use App\Models\User;

class TransactionSourceReferencePolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, TransactionSourceReference $reference): bool
    {
        return $this->canReadFinancialTeam($user, $reference->team);
    }

    public function create(User $user, FinancialAccount $account): bool
    {
        return $account->archived_at === null && $this->canManageFinancialTeam($user, $account->team);
    }

    public function update(User $user, TransactionSourceReference $reference): bool
    {
        return false;
    }

    public function delete(User $user, TransactionSourceReference $reference): bool
    {
        return false;
    }
}
