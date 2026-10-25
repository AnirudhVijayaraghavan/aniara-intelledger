<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_institutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->char('country_code', 2)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'id'], 'fi_team_id_unique');
            $table->index(['team_id', 'archived_at'], 'fi_team_archived_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_institutions');
    }
};
