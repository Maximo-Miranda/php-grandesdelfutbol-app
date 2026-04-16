<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE news_articles SET snippet = '' WHERE snippet IS NULL");
            DB::statement("UPDATE news_articles SET full_content = '' WHERE full_content IS NULL");

            Schema::table('news_articles', function (Blueprint $table) {
                $table->text('snippet')->default('')->nullable(false)->change();
                $table->text('full_content')->default('')->nullable(false)->change();
            });

            DB::statement("
                CREATE INDEX news_articles_fts_idx ON news_articles USING GIN (
                    (to_tsvector('spanish', title) || to_tsvector('spanish', snippet) || to_tsvector('spanish', full_content))
                )
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS news_articles_fts_idx');

            Schema::table('news_articles', function (Blueprint $table) {
                $table->text('snippet')->nullable()->default(null)->change();
                $table->text('full_content')->nullable()->default(null)->change();
            });
        }
    }
};
