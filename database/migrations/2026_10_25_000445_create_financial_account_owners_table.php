<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_account_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_account_id');
            $table->string('owner_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->foreign(['team_id', 'financial_account_id'], 'fao_tenant_account_fk')
                ->references(['team_id', 'id'])->on('financial_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['financial_account_id', 'user_id'], 'fao_account_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_account_owners');
    }
};
