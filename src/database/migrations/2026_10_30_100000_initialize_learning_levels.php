<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['PRE_A1', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $order => $code) {
            DB::table('learning_levels')->insertOrIgnore([
                'code' => $code, 'sort_order' => $order, 'title' => $code === 'PRE_A1' ? 'С нуля' : $code,
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        }
    }

    public function down(): void
    {
        // Fixed reference rows may already be used by published content and progress.
    }
};
