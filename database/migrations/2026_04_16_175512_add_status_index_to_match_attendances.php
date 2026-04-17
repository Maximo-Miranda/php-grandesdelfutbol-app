<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_attendances', function (Blueprint $table) {
            $table->index(['match_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('match_attendances', function (Blueprint $table) {
            $table->dropIndex(['match_id', 'status']);
        });
    }
};
