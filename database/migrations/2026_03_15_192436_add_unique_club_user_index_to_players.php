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
        // Remove duplicate players keeping the oldest record
        DB::statement('
            DELETE FROM players
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM players
                WHERE user_id IS NOT NULL
                GROUP BY club_id, user_id
            )
            AND user_id IS NOT NULL
        ');

        Schema::table('players', function (Blueprint $table) {
            $table->unique(['club_id', 'user_id'], 'players_club_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique('players_club_user_unique');
        });
    }
};
