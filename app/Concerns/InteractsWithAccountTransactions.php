<?php

namespace App\Concerns;

use App\Enums\TransactionKind;
use App\Models\AccountTransaction;
use App\Models\Payee;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Rules\BalanceAmount;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait InteractsWithAccountTransactions
{
    protected function lockTransactionTeam(User $actor, Team $team): Team
    {
        $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [AccountTransaction::class, $team]);

        return $team;
    }

    protected function lockAccountTransaction(User $actor, Team $team, AccountTransaction $transaction, string $ability = 'update'): AccountTransaction
    {
        $record = AccountTransaction::forTeam($team)->whereKey($transaction->id)->firstOrFail();
        $account = $team->financialAccounts()->whereKey($record->financial_account_id)->lockForUpdate()->firstOrFail();
        $record = $account->transactions()->forTeam($team)->whereKey($record->id)->lockForUpdate()->firstOrFail();
        $account->setRelation('team', $team);
        $record->setRelation('team', $team)->setRelation('account', $account);
        Gate::forUser($actor)->authorize($ability, $record);

        return $record;
    }

    /**
     * Only fields selected by the operation are accepted. Identity and audit provenance are server-owned.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, mixed>
     */
    protected function validateTransactionInput(Team $team, array $input, array $rules, ?AccountTransaction $transaction = null): array
    {
        $validated = Arr::only(
            Validator::make($input, $rules)->validate(),
            array_keys(array_filter($rules, fn (array $fieldRules): bool => $fieldRules !== ['prohibited'])),
        );

        if ($transaction !== null) {
            $latest = $transaction->revisions()->reorder()->orderByDesc('revision_number')->lockForUpdate()->firstOrFail();
            if ((int) $validated['expected_revision'] !== $latest->revision_number) {
                throw ValidationException::withMessages(['expected_revision' => 'This transaction has changed. Reload it before trying again.']);
            }
        }

        $categoryId = array_key_exists('category_id', $validated) ? $validated['category_id'] : $transaction?->category_id;
        if ($categoryId !== null) {
            $category = TransactionCategory::forTeam($team)->whereKey($categoryId)->lockForUpdate()->firstOrFail();
            if ($category->getAttribute('archived_at') !== null && $category->id !== $transaction?->category_id) {
                throw ValidationException::withMessages(['category_id' => 'Archived categories cannot be newly assigned.']);
            }
            $kind = $validated['kind'] ?? $transaction?->kind->value ?? TransactionKind::Unclassified->value;
            if ($category->kind->value !== $kind) {
                throw ValidationException::withMessages(['category_id' => 'The category must match the transaction kind.']);
            }
        }

        $payeeId = array_key_exists('payee_id', $validated) ? $validated['payee_id'] : $transaction?->payee_id;
        if ($payeeId !== null) {
            $payee = Payee::forTeam($team)->whereKey($payeeId)->lockForUpdate()->firstOrFail();
            if ($payee->getAttribute('archived_at') !== null && $payee->id !== $transaction?->payee_id) {
                throw ValidationException::withMessages(['payee_id' => 'Archived payees cannot be newly assigned.']);
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, array<mixed>>  $allowedRules
     * @return array<string, array<mixed>>
     */
    protected function transactionInputRules(array $allowedRules, ?AccountTransaction $transaction = null): array
    {
        $fields = [
            'id', 'team_id', 'financial_account_id', 'currency_code', 'recorded_at', 'created_by', 'updated_at',
            'reverses_transaction_id', 'replaces_transaction_id', 'transfer_id', 'transfer_role',
            'amount', 'transaction_date', 'posted_date', 'status', 'kind', 'category_id', 'payee_id',
            'description', 'excluded_from_analytics', 'snapshot', 'actor_id', 'revision_number', 'operation',
        ];
        $rules = array_fill_keys($fields, ['prohibited']);
        $rules['reason'] = ['nullable', 'string', 'max:2000'];
        $rules['expected_revision'] = $transaction === null ? ['prohibited'] : ['required', 'integer', 'min:1', 'max:4294967295'];

        return array_replace($rules, $allowedRules);
    }

    /** @return array<string, array<mixed>> */
    protected function transactionClassificationRules(Team $team, ?AccountTransaction $transaction = null): array
    {
        return [
            'kind' => ['sometimes', 'required', 'string', Rule::enum(TransactionKind::class)],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('transaction_categories', 'id')->where('team_id', $team->id)
                    ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $transaction?->category_id)),
            ],
            'payee_id' => [
                'nullable', 'integer',
                Rule::exists('payees', 'id')->where('team_id', $team->id)
                    ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $transaction?->payee_id)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'excluded_from_analytics' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /** @return array<mixed> */
    protected function transactionAmountRules(): array
    {
        return ['bail', 'required', 'string', 'max:64', new BalanceAmount];
    }

    protected function saveTransaction(AccountTransaction $transaction, User $actor, ?string $reason): AccountTransaction
    {
        $transaction->withRevisionContext($actor, $reason);
        if (! $transaction->save()) {
            throw new DomainException('The transaction could not be saved.');
        }

        return $transaction;
    }
}
