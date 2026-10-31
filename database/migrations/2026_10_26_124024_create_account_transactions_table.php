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
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->unique(['team_id', 'id', 'currency_code'], 'fa_team_currency_unique');
        });

        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::create('account_transactions', function (Blueprint $table) use ($sqlite): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_account_id');
            $table->char('currency_code', 3);
            /** SQLite NUMERIC affinity would lose precision for large decimal strings. */
            $table->rawColumn('amount', $sqlite ? 'TEXT' : 'DECIMAL(24,8)');
            $table->enum('kind', ['income', 'expense', 'transfer', 'adjustment', 'unclassified'])->default('unclassified');
            $table->enum('status', ['pending', 'posted', 'cancelled'])->default('pending');
            $table->date('transaction_date');
            $table->rawColumn('posted_date', $sqlite ? "DATE CHECK (status <> 'posted' OR posted_date IS NOT NULL)" : 'DATE')->nullable();
            $table->dateTime('recorded_at', 6);
            $table->string('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->unsignedBigInteger('reverses_transaction_id')->nullable();
            $table->unsignedBigInteger('replaces_transaction_id')->nullable();
            $table->boolean('excluded_from_analytics')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['team_id', 'id'], 'at_team_id_unique');
            $table->unique(['team_id', 'financial_account_id', 'id'], 'at_account_id_unique');
            $table->unique('reverses_transaction_id', 'at_reversal_unique');
            $table->unique('replaces_transaction_id', 'at_replacement_unique');
            $table->index(['team_id', 'financial_account_id', 'status', 'posted_date', 'id'], 'at_account_posted_index');
            $table->index(['team_id', 'transaction_date', 'id'], 'at_team_history_index');
            $table->index(['team_id', 'category_id', 'posted_date', 'id'], 'at_category_index');
            $table->foreign(['team_id', 'financial_account_id', 'currency_code'], 'at_tenant_account_currency_fk')
                ->references(['team_id', 'id', 'currency_code'])->on('financial_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['team_id', 'category_id'], 'at_tenant_category_fk')
                ->references(['team_id', 'id'])->on('transaction_categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['team_id', 'payee_id'], 'at_tenant_payee_fk')
                ->references(['team_id', 'id'])->on('payees')->restrictOnDelete()->restrictOnUpdate();
            foreach (['reverses_transaction_id' => 'at_reversal_fk', 'replaces_transaction_id' => 'at_replacement_fk'] as $column => $name) {
                $table->foreign(['team_id', 'financial_account_id', $column], $name)
                    ->references(['team_id', 'financial_account_id', 'id'])->on('account_transactions')->restrictOnDelete()->restrictOnUpdate();
            }
        });

        if (! $sqlite) {
            DB::statement("ALTER TABLE account_transactions ADD CONSTRAINT at_posted_date_check CHECK (status <> 'posted' OR posted_date IS NOT NULL)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transactions');

        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropUnique('fa_team_currency_unique');
        });
    }
};
