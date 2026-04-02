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
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('auto_cancel')->default(true)->after('next_match_created_at');
            $table->unsignedSmallInteger('min_players_required')->default(10)->after('auto_cancel');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['auto_cancel', 'min_players_required']);
        });
    }
};
