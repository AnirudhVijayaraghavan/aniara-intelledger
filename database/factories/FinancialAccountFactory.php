<?php

namespace Database\Factories;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialAccount> */
class FinancialAccountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'financial_institution_id' => null,
            'name' => 'Household checking',
            'type' => FinancialAccountType::Checking,
            'currency_code' => 'USD',
            'created_by' => null,
            'archived_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FinancialAccount $account): void {
            $account->owners()->create([
                'team_id' => $account->team_id,
                'owner_name' => 'Recorded account holder',
            ]);
        });
    }
}
