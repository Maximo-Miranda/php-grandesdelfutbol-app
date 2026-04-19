<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('matches_count')->default(15);
            $table->string('status')->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX seasons_one_active_per_club ON seasons (club_id) WHERE status = 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS seasons_one_active_per_club');
        }

        Schema::dropIfExists('seasons');
    }
};
