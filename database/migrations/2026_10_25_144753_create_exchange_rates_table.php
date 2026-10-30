<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::create('exchange_rates', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->char('base_currency_code', 3);
            $table->char('quote_currency_code', 3);
            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('quote_currency_code')->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            /** SQLite stores exact text; the numeric CHECK only validates positivity. */
            $table->rawColumn('rate', $sqlite ? 'TEXT CHECK (CAST(rate AS NUMERIC) > 0)' : 'DECIMAL(36,18) CHECK (rate > 0)');
            $table->dateTime('effective_at', 6);
            $table->string('source', 32)->default('manual');
            $table->dateTime('recorded_at', 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['team_id', 'base_currency_code', 'quote_currency_code', 'source', 'effective_at', 'recorded_at'], 'fx_observation_unique');
            $table->index(['team_id', 'base_currency_code', 'quote_currency_code', 'effective_at', 'recorded_at', 'id'], 'fx_pair_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
