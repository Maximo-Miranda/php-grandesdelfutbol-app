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
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('own_goals')->default(0)->after('handballs');
            $table->unsignedInteger('penalties_scored')->default(0)->after('own_goals');
            $table->unsignedInteger('penalties_missed')->default(0)->after('penalties_scored');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['own_goals', 'penalties_scored', 'penalties_missed']);
        });
    }
};
