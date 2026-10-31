<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->char('reporting_currency_code', 3)->default('USD');
            $table->foreign('reporting_currency_code')->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropForeign(['reporting_currency_code']);
            $table->dropColumn('reporting_currency_code');
        });
    }
};
