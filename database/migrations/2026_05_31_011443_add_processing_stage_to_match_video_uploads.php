<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->string('processing_stage')->nullable()->after('status');
            $table->timestamp('processing_heartbeat_at')->nullable()->after('processing_stage');
        });
    }

    public function down(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->dropColumn(['processing_stage', 'processing_heartbeat_at']);
        });
    }
};
