<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_reels', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('match_events')->nullOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('title');
            $table->unsignedInteger('start_second');
            $table->unsignedInteger('end_second');
            $table->unsignedSmallInteger('duration');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_reels');
    }
};
