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
        Schema::create('transaction_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_transaction_id');
            $table->unsignedInteger('revision_number');
            $table->enum('operation', ['created', 'updated']);
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at', 6);
            $table->unique(['account_transaction_id', 'revision_number'], 'tr_sequence_unique');
            $table->index(['team_id', 'account_transaction_id', 'recorded_at', 'id'], 'tr_history_index');
            $table->foreign(['team_id', 'account_transaction_id'], 'tr_tenant_transaction_fk')
                ->references(['team_id', 'id'])->on('account_transactions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_revisions');
    }
};
