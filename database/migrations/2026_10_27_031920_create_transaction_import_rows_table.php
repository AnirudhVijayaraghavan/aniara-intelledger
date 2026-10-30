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
        Schema::create('transaction_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_account_id');
            $table->unsignedBigInteger('transaction_import_batch_id');
            $table->rawColumn('record_number', 'INTEGER CHECK (record_number >= 1)');
            $table->rawColumn('mapping_version', 'INTEGER CHECK (mapping_version >= 1)')->default(1);
            $table->text('original_fields');
            $table->text('normalized_data')->nullable();
            $table->text('validation_errors')->nullable();
            $table->text('validation_warnings')->nullable();
            $table->text('duplicate_candidates')->nullable();
            $table->char('candidate_fingerprint', 64)->nullable();
            $table->enum('status', ['pending', 'valid', 'invalid', 'needs_review', 'approved', 'imported', 'linked', 'skipped', 'failed'])->default('pending');
            $table->enum('disposition', ['import', 'link', 'skip', 'post_pending'])->nullable();
            $table->unsignedBigInteger('account_transaction_id')->nullable();
            $table->rawColumn('applied_revision_number', "INTEGER CHECK (
                (account_transaction_id IS NULL AND applied_revision_number IS NULL AND status NOT IN ('imported', 'linked'))
                OR (account_transaction_id IS NOT NULL AND applied_revision_number IS NOT NULL AND applied_revision_number >= 1
                    AND processed_at IS NOT NULL AND disposition IS NOT NULL
                    AND ((status = 'imported' AND disposition = 'import') OR (status = 'linked' AND disposition IN ('link', 'post_pending'))))
            )")->nullable();
            $table->rawColumn('expected_revision', 'INTEGER CHECK (expected_revision >= 1)')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at', 6)->nullable();
            $table->text('decision_reason')->nullable();
            $table->rawColumn('attempts', 'INTEGER CHECK (attempts >= 0)')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->dateTime('processed_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['transaction_import_batch_id', 'record_number'], 'tir_record_unique');
            $table->unique(['team_id', 'financial_account_id', 'id', 'account_transaction_id'], 'tir_outcome_unique');
            $table->index(['transaction_import_batch_id', 'status', 'record_number'], 'tir_progress_index');
            $table->index(['team_id', 'financial_account_id', 'candidate_fingerprint'], 'tir_candidate_index');
            $table->foreign(['team_id', 'financial_account_id', 'transaction_import_batch_id'], 'tir_batch_fk')
                ->references(['team_id', 'financial_account_id', 'id'])->on('transaction_import_batches')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['team_id', 'financial_account_id', 'account_transaction_id'], 'tir_transaction_fk')
                ->references(['team_id', 'financial_account_id', 'id'])->on('account_transactions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['account_transaction_id', 'applied_revision_number'], 'tir_revision_fk')
                ->references(['account_transaction_id', 'revision_number'])->on('transaction_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_import_rows');
    }
};
