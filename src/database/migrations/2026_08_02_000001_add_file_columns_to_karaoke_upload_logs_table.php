<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karaoke_upload_logs', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('file_name');
            $table->string('file_mime_type', 100)->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('karaoke_upload_logs', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_mime_type', 'file_size']);
        });
    }
};
