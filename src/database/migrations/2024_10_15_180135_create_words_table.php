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
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_part_of_speech')->nullable()->default(null)->index()->comment('часть речи'); // Часть речи
            $table->string('word')->index()->comment('слово'); // Слово
            $table->string('translation')->nullable()->default(null)->comment('перевод'); // Перевод
            $table->string('transcription')->nullable()->default(null)->comment('транскрипция'); // Транскрипция
            $table->text('example')->nullable()->default(null)->comment('пример'); // Пример
            $table->integer('pronunciation')->default(0)->comment('произношение'); // Произношение

            $table->foreign('id_part_of_speech')->references('id')->on('words_part_of_speeches');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
