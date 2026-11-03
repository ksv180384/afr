<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_content_reports', function (Blueprint $table) {
            $table->uuid('client_report_id')->nullable()->unique('lrep_client_report_uq');
            $table->string('payload_hash', 64)->nullable();
            $table->json('answer_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_content_reports', function (Blueprint $table) {
            $table->dropUnique('lrep_client_report_uq');
            $table->dropColumn(['client_report_id', 'payload_hash', 'answer_snapshot']);
        });
    }
};
