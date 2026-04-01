<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karaoke_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('song_id')->index()->comment('ID песни');
            $table->string('song_title')->comment('Название песни');
            $table->string('song_artist')->comment('Исполнитель');
            $table->string('file_name')->comment('Название загруженного файла');
            $table->decimal('file_duration_seconds', 10, 2)->comment('Продолжительность в файле (сек)');
            $table->unsignedInteger('db_duration_seconds')->nullable()->comment('Продолжительность в БД (сек)');
            $table->boolean('duration_matched')->comment('Совпало ли время с БД');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('ID пользователя (если авторизован)');
            $table->timestamps();

            $table->foreign('song_id')->references('id')->on('player_songs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karaoke_upload_logs');
    }
};
