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
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('news_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('snippet')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('original_url', 2048);
            $table->string('author')->nullable();
            $table->string('content_type');
            $table->string('video_embed_url', 2048)->nullable();
            $table->json('tags')->nullable();
            $table->json('competitions')->nullable();
            $table->json('teams')->nullable();
            $table->json('topics')->nullable();
            $table->boolean('is_breaking')->default(false);
            $table->text('ai_summary')->nullable();
            $table->string('story_group_id')->nullable()->index();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['news_source_id', 'external_id']);
            $table->index('published_at');
            $table->index('content_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
