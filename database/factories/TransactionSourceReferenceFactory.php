<?php

namespace Database\Factories;

use App\Models\AccountTransaction;
use App\Models\TransactionSourceReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionSourceReference> */
class TransactionSourceReferenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'account_transaction_id' => AccountTransaction::factory(),
            'financial_account_id' => fn (array $attributes): int => AccountTransaction::query()->whereKey($attributes['account_transaction_id'])->firstOrFail()->financial_account_id,
            'team_id' => fn (array $attributes): int => AccountTransaction::query()->whereKey($attributes['account_transaction_id'])->firstOrFail()->team_id,
            'source_namespace' => 'csv', 'source_key_hash' => hash('sha256', fake()->uuid()),
            'identity_version' => 1, 'recorded_at' => now()->utc(),
        ];
    }
}
