<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('field_id')->constrained('seasons')->nullOnDelete();
            $table->foreignId('team_a_id')->nullable()->after('season_id')->constrained('teams')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->after('team_a_id')->constrained('teams')->nullOnDelete();
            $table->boolean('is_friendly')->default(false)->after('team_b_id');

            $table->index(['season_id', 'status']);
            $table->index(['team_a_id', 'season_id']);
            $table->index(['team_b_id', 'season_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->string('team_b_name', 50)->nullable()->change();
            $table->string('team_b_color', 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('team_b_name', 50)->default('Equipo B')->nullable(false)->change();
            $table->string('team_b_color', 7)->default('#facc15')->nullable(false)->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropForeign(['team_a_id']);
            $table->dropForeign(['team_b_id']);
            $table->dropIndex(['season_id', 'status']);
            $table->dropIndex(['team_a_id', 'season_id']);
            $table->dropIndex(['team_b_id', 'season_id']);
            $table->dropColumn(['season_id', 'team_a_id', 'team_b_id', 'is_friendly']);
        });
    }
};
