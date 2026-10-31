<?php

namespace Database\Factories;

use App\Enums\TransactionRevisionOperation;
use App\Models\AccountTransaction;
use App\Models\TransactionRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionRevision> */
class TransactionRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'account_transaction_id' => AccountTransaction::factory(),
            'team_id' => fn (array $attributes): int => AccountTransaction::query()->whereKey($attributes['account_transaction_id'])->firstOrFail()->team_id,
            'revision_number' => fn (array $attributes): int => ((int) AccountTransaction::query()->whereKey($attributes['account_transaction_id'])->firstOrFail()->revisions()->max('revision_number')) + 1,
            'operation' => TransactionRevisionOperation::Updated,
            'snapshot' => fn (array $attributes): array => AccountTransaction::query()->whereKey($attributes['account_transaction_id'])->firstOrFail()->canonicalSnapshot(),
            'actor_id' => null,
            'reason' => 'Audit annotation',
            'recorded_at' => now(),
        ];
    }
}
