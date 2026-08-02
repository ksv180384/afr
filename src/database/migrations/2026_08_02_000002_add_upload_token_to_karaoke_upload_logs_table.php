<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karaoke_upload_logs', function (Blueprint $table) {
            $table->string('upload_token', 64)->nullable()->unique()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('karaoke_upload_logs', function (Blueprint $table) {
            $table->dropUnique(['upload_token']);
            $table->dropColumn('upload_token');
        });
    }
};
