<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Enums\TransactionKind;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;

class AccountTransactionPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, AccountTransaction $transaction): bool
    {
        return $this->canReadFinancialTeam($user, $transaction->team);
    }

    public function create(User $user, FinancialAccount $account): bool
    {
        return $account->archived_at === null && $this->canManageFinancialTeam($user, $account->team);
    }

    public function createAny(User $user, Team $team): bool
    {
        return $this->canManageFinancialTeam($user, $team);
    }

    public function update(User $user, AccountTransaction $transaction): bool
    {
        return $transaction->account->archived_at === null && $this->canManageFinancialTeam($user, $transaction->team)
            && ! $transaction->isTransferLeg() && ! $transaction->isInReversalPair();
    }

    public function reverse(User $user, AccountTransaction $transaction): bool
    {
        return $transaction->account->archived_at === null && $this->canManageFinancialTeam($user, $transaction->team)
            && $transaction->kind !== TransactionKind::Transfer && ! $transaction->isTransferLeg();
    }

    public function delete(User $user, AccountTransaction $transaction): bool
    {
        return false;
    }
}
