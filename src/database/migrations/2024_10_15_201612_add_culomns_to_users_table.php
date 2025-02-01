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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 500)->nullable()->default(null)->comment('аватар пользователя');
            $table->unsignedBigInteger('gender_id')->nullable()->default(null)->comment('пол пользователя');
            $table->timestamp('birthday')->nullable()->default(null)->comment('дата рождения пользователя');
            $table->text('info')->nullable()->default(null)->comment('информация о себе'); // Информация о себе
            $table->text('signature')->nullable()->default(null)->comment('Подпись отображается под сообщениями форума');
            $table->string('residence', 500)->nullable()->default(null)->comment('место жительства'); // Место жительства
            $table->unsignedBigInteger('rang_id')->index()->nullable()->default(null)->comment('ранг пользователя');
            $table->boolean('admin')->default(false)->comment('является ли пользователь администратором');

            $table->foreign('gender_id')->references('id')->on('genders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
