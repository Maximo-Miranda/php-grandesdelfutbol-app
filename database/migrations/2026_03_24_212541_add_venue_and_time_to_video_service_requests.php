<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_service_requests', function (Blueprint $table) {
            $table->string('preferred_time')->nullable()->after('preferred_date');
            $table->string('venue_address')->nullable()->after('club_name');
            $table->foreignId('user_id')->nullable()->after('ulid')->constrained()->nullOnDelete();
            $table->string('club_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_service_requests', function (Blueprint $table) {
            $table->dropColumn(['preferred_time', 'venue_address']);
            $table->dropConstrainedForeignId('user_id');
            $table->string('club_name')->change();
        });
    }
};
