<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ntfy_token', 26)->nullable()->after('last_club_id');
            $table->timestamp('ntfy_enabled_at')->nullable()->after('ntfy_token');
        });

        // Generate tokens for existing users
        DB::table('users')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'ntfy_token' => Str::random(26),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('ntfy_token', 26)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ntfy_token', 'ntfy_enabled_at']);
        });
    }
};
