<?php

namespace App\Actions\Finance;

use App\Concerns\FinancialAccountValidationRules;
use App\Models\FinancialAccount;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateFinancialAccount
{
    use FinancialAccountValidationRules;

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, array $input): FinancialAccount
    {
        return DB::transaction(function () use ($actor, $team, $input): FinancialAccount {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [FinancialAccount::class, $team]);

            $validator = Validator::make($input, $this->financialAccountRules($team));
            $this->configureFinancialAccountValidator($validator, $team);
            $validated = $validator->validate();

            $institutionId = $validated['financial_institution_id'] ?? null;
            if (! empty($validated['institution'])) {
                Gate::forUser($actor)->authorize('create', [FinancialInstitution::class, $team]);
                $institutionId = $team->financialInstitutions()->create($validated['institution'])->id;
            }

            $account = $team->financialAccounts()->create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'currency_code' => $validated['currency_code'],
                'financial_institution_id' => $institutionId,
                'created_by' => $actor->id,
            ]);

            foreach ($validated['owners'] as $owner) {
                $account->owners()->create([
                    'team_id' => $team->id,
                    'owner_name' => $owner['owner_name'],
                    'user_id' => $owner['user_id'] ?? null,
                ]);
            }

            return $account->load(['owners', 'currency', 'institution']);
        }, attempts: 3);
    }
}
