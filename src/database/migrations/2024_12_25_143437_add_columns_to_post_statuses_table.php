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
        Schema::table('post_statuses', function (Blueprint $table) {
            $table->boolean('for_create')->default(true)->comment('Статус виден при добавлении поста');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_statuses', function (Blueprint $table) {
            $table->dropColumn('for_create');
        });
    }
};
