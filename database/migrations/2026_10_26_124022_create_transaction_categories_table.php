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
        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('kind', ['income', 'expense']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'id'], 'tc_team_id_unique');
            $table->index(['team_id', 'parent_id', 'archived_at'], 'tc_parent_index');
            $table->foreign(['team_id', 'parent_id'], 'tc_tenant_parent_fk')
                ->references(['team_id', 'id'])->on('transaction_categories')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_categories');
    }
};
