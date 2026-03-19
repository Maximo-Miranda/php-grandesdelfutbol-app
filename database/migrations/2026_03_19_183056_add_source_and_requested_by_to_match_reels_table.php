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
        Schema::table('match_reels', function (Blueprint $table) {
            $table->string('source')->default('auto')->after('status');
            $table->foreignId('requested_by')->nullable()->after('player_id')->constrained('users')->nullOnDelete();
            $table->text('request_notes')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('match_reels', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['source', 'requested_by', 'request_notes']);
        });
    }
};
