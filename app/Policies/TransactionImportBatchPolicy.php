<?php

namespace App\Policies;

use App\Concerns\AuthorizesFinancialTeams;
use App\Enums\TransactionImportBatchStatus;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionImportBatch;
use App\Models\User;

class TransactionImportBatchPolicy
{
    use AuthorizesFinancialTeams;

    public function viewAny(User $user, Team $team): bool
    {
        return $this->canReadFinancialTeam($user, $team);
    }

    public function view(User $user, TransactionImportBatch $batch): bool
    {
        return $this->canReadFinancialTeam($user, $batch->team);
    }

    public function create(User $user, FinancialAccount $account): bool
    {
        return $account->archived_at === null && $this->canManageFinancialTeam($user, $account->team);
    }

    public function update(User $user, TransactionImportBatch $batch): bool
    {
        return $batch->account->archived_at === null && $batch->status !== TransactionImportBatchStatus::Processing
            && $this->canManageFinancialTeam($user, $batch->team);
    }

    public function reprocess(User $user, TransactionImportBatch $batch): bool
    {
        return $this->update($user, $batch) && $batch->file_path !== null && $batch->file_purged_at === null;
    }

    public function viewSourceData(User $user, TransactionImportBatch $batch): bool
    {
        return $this->canManageFinancialTeam($user, $batch->team);
    }

    public function delete(User $user, TransactionImportBatch $batch): bool
    {
        return false;
    }
}
