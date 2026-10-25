<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialAccountOwner> */
class FinancialAccountOwnerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_account_id' => FinancialAccount::factory(),
            'team_id' => fn (array $attributes): int => FinancialAccount::query()->whereKey($attributes['financial_account_id'])->firstOrFail()->team_id,
            'owner_name' => fake()->name(),
            'user_id' => null,
        ];
    }
}
