<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('color', 7);
            $table->foreignId('coach_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('captain_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'normalized_name']);
            $table->index(['club_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
