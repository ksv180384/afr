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
        Schema::create('onlines', function (Blueprint $table) {
            $table->string('token')->primary()->comment('Токен пользователя');
            $table->unsignedBigInteger('user_id')->nullable()->default(null)->comment('Идентификатор пользователя');
            $table->string('ip', 100)->nullable()->default(null)->comment('ip пользователя');
            $table->boolean('is_bot')->default(false)->default(null)->comment('Является ли пользователь ботом');
            $table->string('bot_name', 255)->nullable()->default(null)->comment('Название бота');
            $table->timestamp('date')->useCurrent()->comment('Дата последнего посещения.');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onlines');
    }
};
