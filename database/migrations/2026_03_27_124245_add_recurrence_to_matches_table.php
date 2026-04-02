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
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(true)->after('auto_started');
            $table->unsignedSmallInteger('recurrence_days')->default(8)->after('is_recurring');
            $table->timestamp('next_match_created_at')->nullable()->after('recurrence_days');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'recurrence_days', 'next_match_created_at']);
        });
    }
};
