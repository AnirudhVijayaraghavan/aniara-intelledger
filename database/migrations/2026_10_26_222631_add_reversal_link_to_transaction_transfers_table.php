<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $check = 'reverses_transfer_id IS NULL OR (reverses_transfer_id <> id AND fx_rate IS NULL AND fx_base_currency_code IS NULL AND fx_quote_currency_code IS NULL AND fx_source IS NULL AND fx_effective_at IS NULL)';

        Schema::table('transaction_transfers', function (Blueprint $table) use ($sqlite, $check): void {
            $table->rawColumn('reverses_transfer_id', $sqlite ? 'INTEGER CHECK ('.$check.')' : 'BIGINT')->nullable();
            $table->unique('reverses_transfer_id', 'tt_reversal_unique');
            $table->foreign(['team_id', 'reverses_transfer_id'], 'tt_tenant_reversal_fk')
                ->references(['team_id', 'id'])->on('transaction_transfers')->restrictOnDelete()->restrictOnUpdate();
            if ($sqlite) {
                $this->preserveSqliteChecks($table);
            }
        });

        if (! $sqlite) {
            DB::statement('ALTER TABLE transaction_transfers ADD CONSTRAINT tt_reversal_check CHECK ('.$check.')');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('transaction_transfers')->whereNotNull('reverses_transfer_id')->exists()) {
            throw new RuntimeException('Cannot remove reversal links while transfer reversal history exists.');
        }
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        if (! $sqlite) {
            DB::statement('ALTER TABLE transaction_transfers DROP CONSTRAINT tt_reversal_check');
        }
        Schema::table('transaction_transfers', function (Blueprint $table) use ($sqlite): void {
            $table->dropForeign($sqlite ? ['team_id', 'reverses_transfer_id'] : 'tt_tenant_reversal_fk');
            $table->dropUnique('tt_reversal_unique');
            $table->dropColumn('reverses_transfer_id');
            if ($sqlite) {
                $this->preserveSqliteChecks($table);
            }
        });
    }

    /** SQLite table rebuilds do not retain raw column CHECK expressions. */
    private function preserveSqliteChecks(Blueprint $table): void
    {
        $completeFx = '(fx_rate IS NULL AND fx_base_currency_code IS NULL AND fx_quote_currency_code IS NULL AND fx_source IS NULL AND fx_effective_at IS NULL) OR (fx_rate IS NOT NULL AND fx_base_currency_code IS NOT NULL AND fx_quote_currency_code IS NOT NULL AND fx_source IS NOT NULL AND fx_effective_at IS NOT NULL AND fx_base_currency_code <> fx_quote_currency_code)';
        $table->rawColumn('destination_account_id', 'INTEGER CHECK (source_account_id <> destination_account_id)')->change();
        $table->rawColumn('fx_rate', 'TEXT CHECK (CAST(fx_rate AS NUMERIC) > 0)')->nullable()->change();
        $table->rawColumn('recorded_at', 'DATETIME CHECK ('.$completeFx.')')->change();
    }
};
