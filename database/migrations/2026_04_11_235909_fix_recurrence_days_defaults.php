<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('matches')
            ->where('recurrence_days', 8)
            ->update(['recurrence_days' => 7]);

        DB::table('matches')
            ->where('recurrence_days', 15)
            ->update(['recurrence_days' => 14]);

        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('recurrence_days')->default(7)->change();
        });
    }

    public function down(): void
    {
        DB::table('matches')
            ->where('recurrence_days', 7)
            ->update(['recurrence_days' => 8]);

        DB::table('matches')
            ->where('recurrence_days', 14)
            ->update(['recurrence_days' => 15]);

        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('recurrence_days')->default(8)->change();
        });
    }
};
