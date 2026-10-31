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
        Schema::create('transaction_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_account_id');
            $table->string('source_namespace', 64)->default('csv');
            $table->char('file_sha256', 64);
            $table->string('file_disk', 64)->default('local');
            $table->text('file_path')->nullable();
            $table->text('original_filename');
            $table->rawColumn('file_size', 'BIGINT CHECK (file_size >= 0)');
            $table->string('parser_version', 32)->default('1');
            $table->rawColumn('mapping_version', 'INTEGER CHECK (mapping_version >= 1)')->default(1);
            $table->text('mapping')->nullable();
            $table->text('mapping_history')->nullable();
            $table->rawColumn('preview_version', 'INTEGER CHECK (preview_version >= 0)')->default(0);
            $table->char('preview_digest', 64)->nullable();
            $table->enum('status', ['uploaded', 'mapping', 'ready_for_review', 'approved', 'processing', 'completed', 'partially_completed', 'failed', 'cancelled'])->default('uploaded');
            $table->rawColumn('total_rows', 'INTEGER CHECK (total_rows >= 0)')->default(0);
            $table->rawColumn('processed_rows', 'INTEGER CHECK (processed_rows >= 0 AND processed_rows <= total_rows)')->default(0);
            foreach (['imported_rows', 'linked_rows', 'skipped_rows', 'failed_rows'] as $column) {
                $table->rawColumn($column, 'INTEGER CHECK ('.$column.' >= 0 AND '.$column.' <= processed_rows)')->default(0);
            }
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at', 6)->nullable();
            $table->rawColumn('approved_mapping_version', "INTEGER CHECK (
                ((approved_at IS NULL AND approved_mapping_version IS NULL AND approved_preview_digest IS NULL AND approved_by IS NULL)
                OR (approved_at IS NOT NULL AND approved_mapping_version IS NOT NULL AND approved_mapping_version = mapping_version
                    AND preview_digest IS NOT NULL AND approved_preview_digest IS NOT NULL AND approved_preview_digest = preview_digest))
                AND (status NOT IN ('approved', 'processing', 'completed', 'partially_completed') OR approved_at IS NOT NULL)
            )")->nullable();
            $table->char('approved_preview_digest', 64)->nullable();
            $table->dateTime('processing_started_at', 6)->nullable();
            $table->dateTime('processing_finished_at', 6)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('file_purged_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['team_id', 'financial_account_id', 'file_sha256'], 'tib_file_unique');
            $table->unique(['team_id', 'financial_account_id', 'id'], 'tib_account_id_unique');
            $table->index(['team_id', 'status', 'id'], 'tib_history_index');
            $table->foreign(['team_id', 'financial_account_id'], 'tib_account_fk')
                ->references(['team_id', 'id'])->on('financial_accounts')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_import_batches');
    }
};
