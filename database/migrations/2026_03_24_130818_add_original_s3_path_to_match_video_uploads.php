<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->string('original_s3_path')->nullable()->after('s3_path');
        });
    }

    public function down(): void
    {
        Schema::table('match_video_uploads', function (Blueprint $table) {
            $table->dropColumn('original_s3_path');
        });
    }
};
