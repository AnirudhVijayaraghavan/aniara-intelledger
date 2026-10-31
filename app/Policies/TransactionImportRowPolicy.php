<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use App\Models\TransactionImportRow;
use App\Models\User;

class TransactionImportRowPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, TransactionImportRow $row): bool
    {
        return $this->canReadFinancialTeam($user, $row->team);
    }

    public function create(User $user, TransactionImportBatch $batch): bool
    {
        return $user->can('update', $batch);
    }

    public function update(User $user, TransactionImportRow $row): bool
    {
        return ! $row->isCommitted() && $user->can('update', $row->batch);
    }

    public function viewSourceData(User $user, TransactionImportRow $row): bool
    {
        return $this->canManageFinancialTeam($user, $row->team);
    }

    public function delete(User $user, TransactionImportRow $row): bool
    {
        return false;
    }
}
