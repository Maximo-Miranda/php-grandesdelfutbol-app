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
            $table->timestamp('registration_opens_at')->nullable()->after('registration_opens_hours');
            $table->unsignedSmallInteger('cancel_hours_before')->nullable()->after('min_players_required');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['registration_opens_at', 'cancel_hours_before']);
        });
    }
};
