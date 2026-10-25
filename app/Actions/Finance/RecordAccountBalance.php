<?php

namespace App\Actions\Finance;

use App\Concerns\AccountBalanceValidationRules;
use App\Enums\AccountBalanceSource;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RecordAccountBalance
{
    use AccountBalanceValidationRules;

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, FinancialAccount $account, array $input): AccountBalance
    {
        return DB::transaction(function () use ($actor, $team, $account, $input): AccountBalance {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $account = $team->financialAccounts()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [AccountBalance::class, $account]);
            $validated = Validator::make($input, $this->accountBalanceRules())->validate();

            return $account->balances()->create([
                'team_id' => $team->id,
                'amount' => $validated['amount'],
                'as_of_date' => $validated['as_of_date'],
                'recorded_at' => now(),
                'source' => AccountBalanceSource::Manual,
                'created_by' => $actor->id,
            ]);
        }, attempts: 3);
    }
}
