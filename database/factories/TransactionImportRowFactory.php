<?php

namespace Database\Factories;

use App\Enums\TransactionImportDisposition;
use App\Enums\TransactionImportRowStatus;
use App\Models\AccountTransaction;
use App\Models\TransactionImportBatch;
use App\Models\TransactionImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionImportRow> */
class TransactionImportRowFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'transaction_import_batch_id' => TransactionImportBatch::factory(),
            'team_id' => fn (array $attributes): int => TransactionImportBatch::query()->whereKey($attributes['transaction_import_batch_id'])->firstOrFail()->team_id,
            'financial_account_id' => fn (array $attributes): int => TransactionImportBatch::query()->whereKey($attributes['transaction_import_batch_id'])->firstOrFail()->financial_account_id,
            'mapping_version' => fn (array $attributes): int => TransactionImportBatch::query()->whereKey($attributes['transaction_import_batch_id'])->firstOrFail()->mapping_version,
            'record_number' => fake()->unique()->numberBetween(1, 1000000),
            'original_fields' => ['Date' => '2026-10-01', 'Amount' => '-100.00000000', 'Description' => 'Statement purchase'],
            'status' => TransactionImportRowStatus::Pending,
        ];
    }

    public function imported(AccountTransaction $transaction): static
    {
        return $this->state([
            'transaction_import_batch_id' => TransactionImportBatch::factory()->state(['financial_account_id' => $transaction->financial_account_id]),
            'account_transaction_id' => $transaction->id,
            'applied_revision_number' => $transaction->revisions()->max('revision_number'),
            'status' => TransactionImportRowStatus::Imported, 'disposition' => TransactionImportDisposition::Import,
            'processed_at' => now(),
        ]);
    }
}
