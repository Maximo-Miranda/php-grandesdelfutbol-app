<?php

use App\Models\NewsArticle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        // Backfill existing articles
        NewsArticle::query()->whereNull('slug')->each(function (NewsArticle $article) {
            $base = Str::slug($article->title);
            $slug = Str::limit($base, 80, '').'-'.substr($article->ulid, -6);

            $article->updateQuietly(['slug' => $slug]);
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
