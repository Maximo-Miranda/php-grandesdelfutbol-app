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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('arrival_minutes')->default(15);
            $table->unsignedSmallInteger('max_players')->default(10);
            $table->unsignedSmallInteger('max_substitutes')->default(4);
            $table->string('status')->default('upcoming');
            $table->string('share_token')->nullable()->unique();
            $table->unsignedSmallInteger('registration_opens_hours')->default(24);
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('stats_finalized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
