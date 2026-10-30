<?php

namespace App\Concerns;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\Team;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait FinancialAccountValidationRules
{
    /** @return array<string, array<mixed>> */
    protected function financialAccountRules(Team $team, ?FinancialAccount $account = null): array
    {
        return [
            'team_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'archived_at' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(FinancialAccountType::class)],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'financial_institution_id' => [
                'nullable', 'integer',
                Rule::exists('financial_institutions', 'id')->where('team_id', $team->id)
                    ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $account?->financial_institution_id)),
            ],
            'institution' => ['nullable', 'array:name,country_code', 'prohibits:financial_institution_id'],
            'institution.name' => ['required_with:institution', 'string', 'max:255'],
            'institution.country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'owners' => ['required', 'array', 'min:1', 'list'],
            'owners.*' => ['required', 'array:id,owner_name,user_id'],
            'owners.*.id' => $account === null ? ['prohibited'] : [
                'nullable', 'integer', 'distinct',
                Rule::exists('financial_account_owners', 'id')->where('team_id', $team->id)->where('financial_account_id', $account->id),
            ],
            'owners.*.owner_name' => ['required', 'string', 'max:255'],
            'owners.*.user_id' => ['nullable', 'integer', 'distinct'],
        ];
    }

    protected function configureFinancialAccountValidator(Validator $validator, Team $team, ?FinancialAccount $account = null): void
    {
        $validator->after(function (Validator $validator) use ($team, $account): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $input = $validator->getData();
            $existingOwners = $account?->owners()->get()->keyBy('id');
            $submittedOwnerIds = [];

            foreach ($input['owners'] as $index => $owner) {
                $userId = $owner['user_id'] ?? null;
                $existing = $existingOwners?->get($owner['id'] ?? null);
                if (isset($owner['id'])) {
                    $submittedOwnerIds[] = $owner['id'];
                }

                if ($userId !== null && (int) $userId !== $existing?->user_id
                    && ! $team->memberships()->where('user_id', $userId)->exists()) {
                    $validator->errors()->add("owners.{$index}.user_id", 'A linked account holder must belong to this household.');
                }
            }

            if ($existingOwners !== null && $existingOwners->keys()->diff($submittedOwnerIds)->isNotEmpty()) {
                $validator->errors()->add('owners', 'Keep all recorded holders. You may edit their details or add holders.');
            }

            if ($account !== null && $account->hasFinancialHistory()) {
                $history = $account->balances()->exists() ? 'balance' : 'transaction';
                if ($input['currency_code'] !== $account->currency_code) {
                    $validator->errors()->add('currency_code', 'Currency cannot change after '.$history.' history exists.');
                }

                if (FinancialAccountType::from($input['type'])->isLiability() !== $account->type->isLiability()) {
                    $validator->errors()->add('type', 'Asset/liability classification cannot change after '.$history.' history exists.');
                }
            }
        });
    }
}
