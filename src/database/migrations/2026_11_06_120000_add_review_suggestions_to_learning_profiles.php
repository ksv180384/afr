<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_profiles', function (Blueprint $table) {
            $table->boolean('review_suggestions_enabled')->default(true);
            $table->dateTime('review_suggestions_snoozed_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_profiles', fn (Blueprint $table) => $table->dropColumn(['review_suggestions_enabled', 'review_suggestions_snoozed_until']));
    }
};
