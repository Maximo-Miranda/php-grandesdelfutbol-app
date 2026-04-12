<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->smallInteger('team_a_score')->nullable()->after('team_a_color');
            $table->smallInteger('team_b_score')->nullable()->after('team_b_color');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['team_a_score', 'team_b_score']);
        });
    }
};
