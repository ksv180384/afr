<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_songs', function (Blueprint $table) {
            $table->decimal('duration', 8, 2)->nullable()->after('title')->comment('продолжительность песни в минутах');
        });
    }

    public function down(): void
    {
        Schema::table('player_songs', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
