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
            $table->string('drive_reels_file_id')->nullable()->after('drive_file_id');
            $table->string('s3_reels_path')->nullable()->after('original_s3_path');
            $table->timestamp('s3_reels_uploaded_at')->nullable();
            $table->timestamp('drive_shared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->dropColumn(['drive_reels_file_id', 's3_reels_path', 's3_reels_uploaded_at', 'drive_shared_at']);
        });
    }
};
