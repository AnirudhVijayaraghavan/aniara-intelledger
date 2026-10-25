<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name', 64);
            $table->unsignedTinyInteger('minor_units');
        });

        DB::table('currencies')->insert([
            ['code' => 'USD', 'name' => 'US Dollar', 'minor_units' => 2],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'minor_units' => 2],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
