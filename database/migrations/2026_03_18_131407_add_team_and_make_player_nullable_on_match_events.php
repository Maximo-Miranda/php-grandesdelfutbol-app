<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->string('team', 1)->nullable()->after('player_id');
        });

        // Make player_id nullable — SQLite requires a rebuild approach
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: recreate the table with nullable player_id
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::statement('CREATE TABLE match_events_temp AS SELECT * FROM match_events');
            Schema::drop('match_events');
            Schema::create('match_events', function (Blueprint $table) {
                $table->id();
                $table->ulid('ulid')->unique();
                $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
                $table->foreignId('player_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('team', 1)->nullable();
                $table->string('event_type');
                $table->unsignedSmallInteger('minute');
                $table->unsignedTinyInteger('second')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
            DB::statement('INSERT INTO match_events SELECT * FROM match_events_temp');
            DB::statement('DROP TABLE match_events_temp');
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            // PostgreSQL/MySQL: just alter the column to nullable, FK constraint stays intact
            Schema::table('match_events', function (Blueprint $table) {
                $table->unsignedBigInteger('player_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::statement('CREATE TABLE match_events_temp AS SELECT * FROM match_events');
            Schema::drop('match_events');
            Schema::create('match_events', function (Blueprint $table) {
                $table->id();
                $table->ulid('ulid')->unique();
                $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
                $table->foreignId('player_id')->constrained()->cascadeOnDelete();
                $table->string('event_type');
                $table->unsignedSmallInteger('minute');
                $table->unsignedTinyInteger('second')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
            DB::statement('INSERT INTO match_events SELECT * FROM match_events_temp WHERE player_id IS NOT NULL');
            DB::statement('DROP TABLE match_events_temp');
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            Schema::table('match_events', function (Blueprint $table) {
                $table->dropColumn('team');
                $table->unsignedBigInteger('player_id')->nullable(false)->change();
            });
        }
    }
};
