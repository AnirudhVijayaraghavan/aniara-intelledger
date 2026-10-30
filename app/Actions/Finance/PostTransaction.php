<?php

namespace App\Actions\Finance;

use App\Concerns\InteractsWithAccountTransactions;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostTransaction
{
    use InteractsWithAccountTransactions;

    /** @return array<string, array<mixed>> */
    public function rules(Team $team, ?AccountTransaction $transaction = null): array
    {
        return $this->transactionInputRules(array_replace($this->transactionClassificationRules($team, $transaction), [
            'amount' => ['sometimes', ...$this->transactionAmountRules()],
            'transaction_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'posted_date' => ['required', 'date_format:Y-m-d'],
        ]), $transaction);
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, AccountTransaction $transaction, array $input): AccountTransaction
    {
        return DB::transaction(function () use ($actor, $team, $transaction, $input): AccountTransaction {
            $team = $this->lockTransactionTeam($actor, $team);
            $transaction = $this->lockAccountTransaction($actor, $team, $transaction);
            $validated = $this->validateTransactionInput($team, $input, $this->rules($team, $transaction), $transaction);
            if ($transaction->status !== TransactionStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Only pending transactions can be posted.']);
            }
            $transaction->fill(Arr::except($validated, ['reason', 'expected_revision']));
            $transaction->status = TransactionStatus::Posted;

            return $this->saveTransaction($transaction, $actor, $validated['reason'] ?? null);
        }, attempts: 3);
    }
}
