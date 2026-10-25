<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite NUMERIC affinity converts decimal strings to floating point.
     * MySQL retains the existing DECIMAL(24,8) column.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('account_balances', function (Blueprint $table): void {
                $table->text('amount')->change();
            });
        }
    }

    /** Restoring SQLite numeric affinity may lose decimal precision. */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('account_balances', function (Blueprint $table): void {
                $table->decimal('amount', 24, 8)->change();
            });
        }
    }
};
