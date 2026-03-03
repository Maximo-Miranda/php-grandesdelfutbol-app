<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('team_a_name', 50)->default('Equipo A');
            $table->string('team_b_name', 50)->default('Equipo B');
            $table->string('team_a_color', 7)->default('#1a1a1a');
            $table->string('team_b_color', 7)->default('#facc15');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['team_a_name', 'team_b_name', 'team_a_color', 'team_b_color']);
        });
    }
};
