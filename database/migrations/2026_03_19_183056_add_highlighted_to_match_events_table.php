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
        Schema::table('match_events', function (Blueprint $table) {
            $table->boolean('highlighted')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropColumn('highlighted');
        });
    }
};
