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
        Schema::table('news_dictionary_entries', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('is_active');
            $table->unsignedInteger('matches_count')->default(0)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('news_dictionary_entries', function (Blueprint $table) {
            $table->dropColumn(['source', 'matches_count']);
        });
    }
};
