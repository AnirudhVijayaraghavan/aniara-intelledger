<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_account_id');
            $table->date('as_of_date');
            $table->dateTime('recorded_at', 6);
            $table->decimal('amount', 24, 8);
            $table->string('source', 16)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['team_id', 'financial_account_id'], 'ab_tenant_account_fk')
                ->references(['team_id', 'id'])->on('financial_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['team_id', 'financial_account_id', 'as_of_date', 'recorded_at', 'id'], 'ab_account_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
