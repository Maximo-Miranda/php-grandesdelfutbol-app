<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_service_requests', function (Blueprint $table) {
            $table->foreignId('match_id')->nullable()->after('user_id')->constrained('matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('video_service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('match_id');
        });
    }
};
