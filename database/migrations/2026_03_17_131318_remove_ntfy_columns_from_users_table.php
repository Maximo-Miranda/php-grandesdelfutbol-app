<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_ntfy_token_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ntfy_token', 'ntfy_enabled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ntfy_token', 26)->unique()->after('last_club_id');
            $table->timestamp('ntfy_enabled_at')->nullable()->after('ntfy_token');
        });
    }
};
