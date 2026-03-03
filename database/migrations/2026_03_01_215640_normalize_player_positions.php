<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapping = [
            'Goalkeeper' => 'GK',
            'Defender' => 'CB',
            'Midfielder' => 'CM',
            'Forward' => 'ST',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('players')->where('position', $old)->update(['position' => $new]);
        }
    }

    public function down(): void
    {
        $mapping = [
            'GK' => 'Goalkeeper',
            'CB' => 'Defender',
            'CM' => 'Midfielder',
            'ST' => 'Forward',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('players')->where('position', $old)->update(['position' => $new]);
        }
    }
};
