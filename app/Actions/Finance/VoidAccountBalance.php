<?php

namespace App\Actions\Finance;

use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VoidAccountBalance
{
    public function handle(User $actor, Team $team, FinancialAccount $account, AccountBalance $balance): AccountBalance
    {
        return DB::transaction(function () use ($actor, $team, $account, $balance): AccountBalance {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $account = $team->financialAccounts()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $balance = $account->balances()->forTeam($team)->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('voidObservation', $balance);
            $balance->update(['voided_at' => now(), 'voided_by' => $actor->id]);

            return $balance;
        }, attempts: 3);
    }
}
