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
        Schema::create('user_infos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary()->comment('идентификатор пользователя');
            $table->string('facebook')->nullable()->default(null);
            $table->string('skype')->nullable()->default(null);
            $table->string('twitter')->nullable()->default(null);
            $table->string('vk')->nullable()->default(null);
            $table->string('odnoklassniki')->nullable()->default(null);
            $table->string('telegram')->nullable()->default(null);
            $table->string('whatsapp')->nullable()->default(null);
            $table->string('viber')->nullable()->default(null);
            $table->string('instagram')->nullable()->default(null);
            $table->string('youtube')->nullable()->default(null);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_infos');
    }
};
