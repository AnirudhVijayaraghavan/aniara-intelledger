<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_institution_id')->nullable();
            $table->string('name');
            $table->string('type', 32);
            $table->char('currency_code', 3);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'id'], 'fa_team_id_unique');
            $table->index(['team_id', 'archived_at'], 'fa_team_archived_index');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['team_id', 'financial_institution_id'], 'fa_tenant_institution_fk')
                ->references(['team_id', 'id'])->on('financial_institutions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
