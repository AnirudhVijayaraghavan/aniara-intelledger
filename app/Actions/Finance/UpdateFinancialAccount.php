<?php

namespace App\Actions\Finance;

use App\Concerns\FinancialAccountValidationRules;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateFinancialAccount
{
    use FinancialAccountValidationRules;

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, FinancialAccount $account, array $input): FinancialAccount
    {
        return DB::transaction(function () use ($actor, $team, $account, $input): FinancialAccount {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $account = $team->financialAccounts()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $account);

            $validator = Validator::make($input, $this->financialAccountRules($team, $account));
            $this->configureFinancialAccountValidator($validator, $team, $account);
            $validated = $validator->validate();

            $institutionId = $validated['financial_institution_id'] ?? null;
            if (! empty($validated['institution'])) {
                Gate::forUser($actor)->authorize('create', [FinancialInstitution::class, $team]);
                $institutionId = $team->financialInstitutions()->create($validated['institution'])->id;
            }

            $account->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'currency_code' => $validated['currency_code'],
                'financial_institution_id' => $institutionId,
            ]);

            $account->owners()->update(['user_id' => null]);
            $owners = $account->owners()->get()->keyBy('id');

            foreach ($validated['owners'] as $index => $inputOwner) {
                $owner = isset($inputOwner['id']) ? $owners->get($inputOwner['id']) : new FinancialAccountOwner;
                if ($owner === null) {
                    throw ValidationException::withMessages([
                        "owners.{$index}.id" => 'The recorded holder could not be found. Reload the account before updating it.',
                    ]);
                }

                $owner->fill([
                    'team_id' => $team->id,
                    'owner_name' => $inputOwner['owner_name'],
                    'user_id' => $inputOwner['user_id'] ?? null,
                ]);
                $account->owners()->save($owner);
            }

            return $account->load(['owners', 'currency', 'institution']);
        }, attempts: 3);
    }
}
