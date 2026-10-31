<?php

namespace App\Actions\Finance;

use App\Concerns\InteractsWithAccountTransactions;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\Team;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseTransaction
{
    use InteractsWithAccountTransactions;

    /** @return array<string, array<mixed>> */
    public function rules(Team $team, ?AccountTransaction $transaction = null): array
    {
        $dateRules = ['required', 'date_format:Y-m-d'];
        if ($transaction?->posted_date !== null) {
            $dateRules[] = 'after_or_equal:'.$transaction->posted_date->toDateString();
        }

        return $this->transactionInputRules([
            'posted_date' => $dateRules,
            'reason' => ['required', 'string', 'max:2000'],
        ], $transaction);
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, Team $team, AccountTransaction $transaction, array $input): AccountTransaction
    {
        return DB::transaction(function () use ($actor, $team, $transaction, $input): AccountTransaction {
            $team = $this->lockTransactionTeam($actor, $team);
            $original = $this->lockAccountTransaction($actor, $team, $transaction, 'reverse');
            $validated = $this->validateTransactionInput($team, $input, $this->rules($team, $original), $original);

            if ($original->status !== TransactionStatus::Posted) {
                throw ValidationException::withMessages(['status' => 'Only posted transactions can be reversed.']);
            }
            if ($original->reverses_transaction_id !== null || $original->replaces_transaction_id !== null
                || $original->replacement()->exists()) {
                throw ValidationException::withMessages(['reversal' => 'Correction transactions cannot be reversed through this workflow.']);
            }
            if ($original->reversal()->exists()) {
                throw ValidationException::withMessages(['reversal' => 'This transaction has already been reversed. Reload it to view the reversal.']);
            }

            $reversal = $original->account->transactions()->make([
                'team_id' => $team->id,
                'currency_code' => $original->currency_code,
                'amount' => (string) BigDecimal::of($original->amount)->negated(),
                'kind' => $original->kind,
                'status' => TransactionStatus::Posted,
                'transaction_date' => $validated['posted_date'],
                'posted_date' => $validated['posted_date'],
                'description' => $original->description,
                'category_id' => $original->category_id,
                'payee_id' => $original->payee_id,
                'excluded_from_analytics' => $original->excluded_from_analytics,
                'reverses_transaction_id' => $original->id,
                'created_by' => $actor->id,
                'recorded_at' => now()->utc(),
            ]);

            return $this->saveTransaction($reversal, $actor, trim($validated['reason']));
        }, attempts: 3);
    }
}
