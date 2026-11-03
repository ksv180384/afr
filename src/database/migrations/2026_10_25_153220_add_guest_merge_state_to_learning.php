<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_sync_episodes', function (Blueprint $table) {
            $table->json('baseline_snapshot')->nullable();
            $table->json('created_word_ids')->nullable();
        });
        Schema::table('learning_runs', function (Blueprint $table) {
            $table->json('import_meta')->nullable();
        });
        Schema::table('personal_vocabulary', function (Blueprint $table) {
            $table->unsignedBigInteger('native_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('personal_vocabulary', fn (Blueprint $table) => $table->dropColumn('native_revision'));
        Schema::table('learning_runs', fn (Blueprint $table) => $table->dropColumn('import_meta'));
        Schema::table('guest_sync_episodes', fn (Blueprint $table) => $table->dropColumn(['baseline_snapshot', 'created_word_ids']));
    }
};
