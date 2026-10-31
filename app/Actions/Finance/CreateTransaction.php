<?php

namespace App\Actions\Finance;

use App\Concerns\InteractsWithAccountTransactions;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateTransaction
{
    use InteractsWithAccountTransactions;

    /** @return array<string, array<mixed>> */
    public function rules(Team $team, ?AccountTransaction $transaction = null): array
    {
        return $this->transactionInputRules(array_replace($this->transactionClassificationRules($team), [
            'amount' => $this->transactionAmountRules(),
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'string', Rule::in([TransactionStatus::Pending->value, TransactionStatus::Posted->value])],
            'posted_date' => ['nullable', 'required_if:status,posted', 'prohibited_unless:status,posted', 'date_format:Y-m-d'],
        ]));
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, FinancialAccount $account, array $input): AccountTransaction
    {
        return DB::transaction(function () use ($actor, $team, $account, $input): AccountTransaction {
            $team = $this->lockTransactionTeam($actor, $team);
            $account = $team->financialAccounts()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [AccountTransaction::class, $account]);
            $input = array_replace(['status' => TransactionStatus::Pending->value], $input);
            $validated = $this->validateTransactionInput($team, $input, $this->rules($team));

            $transaction = $account->transactions()->make(array_replace(
                Arr::except($validated, ['reason', 'expected_revision']),
                ['team_id' => $team->id, 'currency_code' => $account->currency_code, 'created_by' => $actor->id, 'recorded_at' => now()->utc()],
            ));

            return $this->saveTransaction($transaction, $actor, $validated['reason'] ?? null);
        }, attempts: 3);
    }
}
