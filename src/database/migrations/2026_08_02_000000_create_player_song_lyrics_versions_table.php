<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_song_lyrics_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('player_songs')->cascadeOnDelete();
            $table->decimal('duration', 8, 2)->comment('Продолжительность версии песни в минутах');
            $table->text('text_fr')->comment('Текст песни');
            $table->text('text_ru')->comment('Перевод песни');
            $table->text('text_transcription')->comment('Транскрипция песни');
            $table->timestamps();
            $table->index(['song_id', 'duration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_song_lyrics_versions');
    }
};
