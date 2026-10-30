<?php

namespace Database\Factories;

use App\Enums\TransactionKind;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionTransfer> */
class TransactionTransferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'source_account_id' => fn (array $attributes): int => FinancialAccount::factory()->create(['team_id' => $attributes['team_id']])->id,
            'destination_account_id' => fn (array $attributes): int => FinancialAccount::factory()->create(['team_id' => $attributes['team_id']])->id,
            'source_transaction_id' => fn (array $attributes): int => AccountTransaction::factory()->posted()->create([
                'financial_account_id' => $attributes['source_account_id'], 'kind' => TransactionKind::Transfer,
                'amount' => '-100.00000000', 'excluded_from_analytics' => true,
            ])->id,
            'destination_transaction_id' => fn (array $attributes): int => AccountTransaction::factory()->posted()->create([
                'financial_account_id' => $attributes['destination_account_id'], 'kind' => TransactionKind::Transfer,
                'amount' => '100.00000000', 'excluded_from_analytics' => true,
            ])->id,
            'recorded_at' => now()->utc(), 'created_by' => null,
        ];
    }
}
