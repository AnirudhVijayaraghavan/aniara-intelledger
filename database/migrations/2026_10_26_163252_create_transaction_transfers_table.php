<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $fxComplete = '(fx_rate IS NULL AND fx_base_currency_code IS NULL AND fx_quote_currency_code IS NULL AND fx_source IS NULL AND fx_effective_at IS NULL) OR (fx_rate IS NOT NULL AND fx_base_currency_code IS NOT NULL AND fx_quote_currency_code IS NOT NULL AND fx_source IS NOT NULL AND fx_effective_at IS NOT NULL AND fx_base_currency_code <> fx_quote_currency_code)';

        Schema::create('transaction_transfers', function (Blueprint $table) use ($sqlite, $fxComplete): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('source_account_id');
            if ($sqlite) {
                $table->rawColumn('destination_account_id', 'INTEGER CHECK (source_account_id <> destination_account_id)');
            } else {
                $table->unsignedBigInteger('destination_account_id');
            }
            $table->unsignedBigInteger('source_transaction_id');
            $table->unsignedBigInteger('destination_transaction_id');
            $table->char('fx_base_currency_code', 3)->nullable();
            $table->char('fx_quote_currency_code', 3)->nullable();
            $table->rawColumn('fx_rate', $sqlite ? 'TEXT CHECK (CAST(fx_rate AS NUMERIC) > 0)' : 'DECIMAL(36,18) CHECK (fx_rate > 0)')->nullable();
            $table->string('fx_source', 64)->nullable();
            $table->dateTime('fx_effective_at', 6)->nullable();
            if ($sqlite) {
                $table->rawColumn('recorded_at', 'DATETIME CHECK ('.$fxComplete.')');
            } else {
                $table->dateTime('recorded_at', 6);
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['team_id', 'id'], 'tt_team_id_unique');
            $table->unique('source_transaction_id', 'tt_source_transaction_unique');
            $table->unique('destination_transaction_id', 'tt_destination_transaction_unique');
            $table->index(['team_id', 'recorded_at', 'id'], 'tt_team_history_index');
            $table->foreign(['team_id', 'source_account_id', 'source_transaction_id'], 'tt_tenant_source_fk')
                ->references(['team_id', 'financial_account_id', 'id'])->on('account_transactions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['team_id', 'destination_account_id', 'destination_transaction_id'], 'tt_tenant_destination_fk')
                ->references(['team_id', 'financial_account_id', 'id'])->on('account_transactions')->restrictOnDelete()->restrictOnUpdate();
            foreach (['fx_base_currency_code', 'fx_quote_currency_code'] as $column) {
                $table->foreign($column)->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            }
        });

        if (! $sqlite) {
            DB::statement('ALTER TABLE transaction_transfers ADD CONSTRAINT tt_different_accounts CHECK (source_account_id <> destination_account_id)');
            DB::statement('ALTER TABLE transaction_transfers ADD CONSTRAINT tt_complete_fx CHECK ('.$fxComplete.')');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_transfers');
    }
};
