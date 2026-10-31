<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_source_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_account_id');
            $table->unsignedBigInteger('account_transaction_id');
            $table->unsignedBigInteger('transaction_import_row_id')->nullable();
            $table->string('source_namespace', 64);
            $table->char('source_key_hash', 64);
            $table->rawColumn('identity_version', 'INTEGER CHECK (identity_version >= 1)')->default(1);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at', 6);
            $table->unique(['team_id', 'financial_account_id', 'source_namespace', 'source_key_hash'], 'tsr_identity_unique');
            $table->index(['team_id', 'account_transaction_id'], 'tsr_transaction_index');
            $table->foreign(['team_id', 'financial_account_id', 'account_transaction_id'], 'tsr_transaction_fk')
                ->references(['team_id', 'financial_account_id', 'id'])->on('account_transactions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['team_id', 'financial_account_id', 'transaction_import_row_id', 'account_transaction_id'], 'tsr_row_outcome_fk')
                ->references(['team_id', 'financial_account_id', 'id', 'account_transaction_id'])->on('transaction_import_rows')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_source_references');
    }
};
