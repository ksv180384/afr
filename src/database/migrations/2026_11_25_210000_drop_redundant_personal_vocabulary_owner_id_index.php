<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_vocabulary', fn (Blueprint $table) => $table->dropUnique('pv_owner_id_uq'));
    }

    public function down(): void
    {
        Schema::table('personal_vocabulary', fn (Blueprint $table) => $table->unique(['user_id', 'id'], 'pv_owner_id_uq'));
    }
};
