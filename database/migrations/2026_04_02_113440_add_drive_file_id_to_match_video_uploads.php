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
            $table->string('drive_file_id')->nullable()->after('original_s3_path');
        });
    }

    public function down(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->dropColumn('drive_file_id');
        });
    }
};
