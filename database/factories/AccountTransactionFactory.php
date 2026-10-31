<?php

namespace Database\Factories;

use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountTransaction> */
class AccountTransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_account_id' => FinancialAccount::factory(),
            'team_id' => fn (array $attributes): int => FinancialAccount::query()->whereKey($attributes['financial_account_id'])->firstOrFail()->team_id,
            'currency_code' => fn (array $attributes): string => FinancialAccount::query()->whereKey($attributes['financial_account_id'])->firstOrFail()->currency_code,
            'amount' => '-100.00000000',
            'kind' => TransactionKind::Expense,
            'status' => TransactionStatus::Pending,
            'transaction_date' => now()->toDateString(),
            'posted_date' => null,
            'recorded_at' => now(),
            'description' => 'Recorded purchase',
            'category_id' => null,
            'payee_id' => null,
            'reverses_transaction_id' => null,
            'replaces_transaction_id' => null,
            'excluded_from_analytics' => false,
            'created_by' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TransactionStatus::Posted,
            'posted_date' => $attributes['transaction_date'],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => TransactionStatus::Cancelled]);
    }
}
