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
        if (Schema::hasColumn('users', 'news_last_seen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('news_last_seen_at')->nullable()->after('last_club_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('news_last_seen_at');
        });
    }
};
