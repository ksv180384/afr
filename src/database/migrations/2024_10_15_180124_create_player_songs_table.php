<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_songs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('artist_id')->index()->nullable()->default(null)->comment('идентификатор исполнителя');
            $table->string('artist_name')->index()->comment('идентификатор исполнителя');
            $table->string('title')->index()->comment('название песни');
            $table->text('text_fr')->comment('текст песни на французском языке');
            $table->text('text_ru')->comment('текст песни на русском языке');
            $table->text('text_transcription')->comment('транскрипция песни');
            $table->unsignedBigInteger('user_id')->index()->nullable()->default(null)->comment('идентификатор пользователя добавившего');
            $table->boolean('hidden')->default(false)->comment('скрыть/показать');
            $table->timestamps();

            $table->foreign('artist_id')->references('id')->on('player_artists_songs');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `player_songs` ADD FULLTEXT artist_name_title_index(artist_name , title)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_songs');
    }
};
