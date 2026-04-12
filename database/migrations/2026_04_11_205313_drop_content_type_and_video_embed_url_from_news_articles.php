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
        // Drop columns one at a time — SQLite requires separate ALTER statements
        // when dropping columns, and the index needs its own drop call.
        if (Schema::hasColumn('news_articles', 'content_type')) {
            Schema::table('news_articles', function (Blueprint $table) {
                $table->dropIndex(['content_type']);
            });
            Schema::table('news_articles', function (Blueprint $table) {
                $table->dropColumn('content_type');
            });
        }

        if (Schema::hasColumn('news_articles', 'video_embed_url')) {
            Schema::table('news_articles', function (Blueprint $table) {
                $table->dropColumn('video_embed_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('content_type')->default('article')->after('author');
            $table->string('video_embed_url', 2048)->nullable()->after('content_type');
            $table->index('content_type');
        });
    }
};
