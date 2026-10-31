<?php

namespace Database\Factories;

use App\Enums\TransactionImportBatchStatus;
use App\Models\FinancialAccount;
use App\Models\TransactionImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionImportBatch> */
class TransactionImportBatchFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_account_id' => FinancialAccount::factory(),
            'team_id' => fn (array $attributes): int => FinancialAccount::query()->whereKey($attributes['financial_account_id'])->firstOrFail()->team_id,
            'file_sha256' => hash('sha256', fake()->uuid()),
            'file_disk' => 'local', 'file_path' => 'transaction-imports/'.fake()->uuid().'.csv',
            'original_filename' => 'statement.csv', 'file_size' => 1024,
            'status' => TransactionImportBatchStatus::Uploaded, 'source_namespace' => 'csv',
            'parser_version' => '1', 'mapping_version' => 1, 'preview_version' => 0,
            'expires_at' => now()->addDays(30),
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => TransactionImportBatchStatus::Approved,
            'preview_version' => 1, 'preview_digest' => hash('sha256', 'approved preview'),
            'approved_at' => now(), 'approved_mapping_version' => 1,
            'approved_preview_digest' => hash('sha256', 'approved preview'),
        ]);
    }
}
