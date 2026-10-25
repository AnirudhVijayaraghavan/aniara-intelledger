<?php

namespace Database\Factories;

use App\Enums\AccountBalanceSource;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountBalance> */
class AccountBalanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_account_id' => FinancialAccount::factory(),
            'team_id' => fn (array $attributes): int => FinancialAccount::query()->whereKey($attributes['financial_account_id'])->firstOrFail()->team_id,
            'as_of_date' => now()->toDateString(),
            'recorded_at' => now(),
            'amount' => '100.00000000',
            'source' => AccountBalanceSource::Manual,
            'created_by' => null,
            'voided_at' => null,
            'voided_by' => null,
        ];
    }
}
