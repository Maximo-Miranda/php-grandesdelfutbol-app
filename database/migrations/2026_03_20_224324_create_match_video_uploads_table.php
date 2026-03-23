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
        Schema::create('match_video_uploads', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('football_match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('bunny_video_id')->nullable();
            $table->string('status')->default('uploading');
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('original_size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->integer('video_offset_seconds')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('encoded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_video_uploads');
    }
};
