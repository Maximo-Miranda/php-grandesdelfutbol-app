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
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->string('youtube_video_id')->nullable()->after('encoded_at');
            $table->timestamp('youtube_uploaded_at')->nullable()->after('youtube_video_id');
            $table->string('s3_path')->nullable()->after('youtube_uploaded_at');
            $table->string('best_resolution')->nullable()->after('s3_path');
            $table->timestamp('bunny_deleted_at')->nullable()->after('best_resolution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_video_id',
                'youtube_uploaded_at',
                's3_path',
                'best_resolution',
                'bunny_deleted_at',
            ]);
        });
    }
};
