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
        Schema::table('news_articles', function (Blueprint $table) {
            if (Schema::hasColumn('news_articles', 'content_type')) {
                $table->dropIndex('news_articles_content_type_index');
                $table->dropColumn('content_type');
            }

            if (Schema::hasColumn('news_articles', 'video_embed_url')) {
                $table->dropColumn('video_embed_url');
            }
        });
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
