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

class ClassifyTransaction
{
    use InteractsWithAccountTransactions;

    /** @return array<string, array<mixed>> */
    public function rules(Team $team, ?AccountTransaction $transaction = null): array
    {
        return $this->transactionInputRules($this->transactionClassificationRules($team, $transaction), $transaction);
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, AccountTransaction $transaction, array $input): AccountTransaction
    {
        return DB::transaction(function () use ($actor, $team, $transaction, $input): AccountTransaction {
            $team = $this->lockTransactionTeam($actor, $team);
            $transaction = $this->lockAccountTransaction($actor, $team, $transaction);
            $validated = $this->validateTransactionInput($team, $input, $this->rules($team, $transaction), $transaction);
            if ($transaction->status === TransactionStatus::Cancelled) {
                throw ValidationException::withMessages(['status' => 'Cancelled transactions cannot be classified.']);
            }
            $transaction->fill(Arr::except($validated, ['reason', 'expected_revision']));

            return $this->saveTransaction($transaction, $actor, $validated['reason'] ?? null);
        }, attempts: 3);
    }
}
