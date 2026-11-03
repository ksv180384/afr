<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_runs', function (Blueprint $table) {
            $table->unsignedInteger('cursor_position')->default(1);
            $table->json('introductions_seen')->nullable();
        });
        Schema::create('learning_run_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_event_id');
            $table->uuid('run_id')->nullable();
            $table->foreign(['user_id', 'run_id'], 'lro_run_owner_fk')->references(['user_id', 'id'])->on('learning_runs')->cascadeOnDelete();
            $table->string('operation', 32);
            $table->char('payload_hash', 64);
            $table->json('receipt');
            $table->dateTime('received_at');
            $table->unique(['user_id', 'client_event_id'], 'lro_owner_event_uq');
            $table->index(['user_id', 'run_id', 'id'], 'lro_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_run_operations');
        Schema::table('learning_runs', fn (Blueprint $table) => $table->dropColumn(['cursor_position', 'introductions_seen']));
    }
};
