<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_vocabulary', function (Blueprint $table) {
            $table->boolean('is_difficult')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('personal_vocabulary', fn (Blueprint $table) => $table->dropColumn('is_difficult'));
    }
};
