<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_runs', fn (Blueprint $table) => $table->json('corrections')->nullable());
    }

    public function down(): void
    {
        Schema::table('learning_runs', fn (Blueprint $table) => $table->dropColumn('corrections'));
    }
};
