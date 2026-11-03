<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $identifierCollation = Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin';

        Schema::create('learning_level_events', function (Blueprint $table) use ($identifierCollation) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('old_level', 8)->collation($identifierCollation)->nullable();
            $table->foreign('old_level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->string('new_level', 8)->collation($identifierCollation);
            $table->foreign('new_level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->string('source', 32);
            $table->json('reason')->nullable();
            $table->uuid('source_event_id');
            $table->dateTime('occurred_at');
            $table->unique(['user_id', 'source_event_id'], 'lle_source_uq');
            $table->unique(['user_id', 'id'], 'lle_owner_id_uq');
            $table->index(['user_id', 'occurred_at'], 'lle_history_idx');
            $table->timestamps();
        });
        Schema::create('learning_profiles', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique('user_id');
            $table->string('current_level', 8)->collation($identifierCollation)->default('PRE_A1');
            $table->foreign('current_level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedBigInteger('revision')->default(1);
            $table->uuid('last_manual_level_event_id')->nullable();
            $table->foreign(['user_id', 'last_manual_level_event_id'], 'lp_manual_event_fk')->references(['user_id', 'id'])->on('learning_level_events')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('learning_curriculum_enrollments', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('level', 8)->collation($identifierCollation);
            $table->foreign('level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->unsignedBigInteger('curriculum_version_id');
            $table->foreign(['curriculum_version_id', 'level'], 'lce_curriculum_level_fk')->references(['id', 'level'])->on('learning_curricula')->restrictOnDelete();
            $table->unsignedInteger('promotion_policy_version');
            $table->json('promotion_policy_snapshot');
            $table->unsignedBigInteger('revision')->default(1);
            $table->dateTime('enrolled_at');
            $table->dateTime('superseded_at')->nullable();
            $table->unique(['user_id', 'level', 'curriculum_version_id'], 'lce_enrollment_uq');
            $table->unique(['user_id', 'id'], 'lce_owner_id_uq');
            $table->index(['user_id', 'level', 'superseded_at'], 'lce_current_idx');
            $table->timestamps();
        });
        Schema::create('learning_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32)->default('daily');
            $table->string('status', 16)->default('in_progress');
            $table->foreignId('session_version_id')->nullable()->constrained('learning_session_versions')->restrictOnDelete();
            $table->json('snapshot');
            $table->unsignedInteger('checking_policy_version')->default(1);
            $table->json('checking_policy_snapshot');
            $table->string('timezone', 64)->default('UTC');
            $table->date('local_date');
            $table->dateTime('started_at');
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->json('result')->nullable();
            $table->unique(['user_id', 'id'], 'lr_owner_id_uq');
            $table->unique(['id', 'session_version_id'], 'lr_id_version_uq');
            $table->index(['user_id', 'status', 'started_at'], 'lr_owner_status_idx');
            $table->timestamps();
        });
        Schema::create('daily_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('local_date');
            $table->uuid('selected_run_id')->nullable();
            $table->foreign(['user_id', 'selected_run_id'], 'da_selected_owner_fk')->references(['user_id', 'id'])->on('learning_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('revision')->default(1);
            $table->unique(['user_id', 'local_date'], 'da_owner_date_uq');
            $table->timestamps();
        });
        Schema::create('learning_presentations', function (Blueprint $table) use ($identifierCollation) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('run_id');
            $table->foreign(['user_id', 'run_id'], 'lpr_run_owner_fk')->references(['user_id', 'id'])->on('learning_runs')->cascadeOnDelete();
            $table->string('task_id', 128)->collation($identifierCollation);
            $table->unsignedInteger('position');
            $table->json('snapshot');
            $table->json('choice_order')->nullable();
            $table->string('status', 24)->default('pending');
            $table->boolean('help_used')->default(false);
            $table->unique(['run_id', 'position'], 'lpr_run_position_uq');
            $table->unique(['user_id', 'run_id', 'id'], 'lpr_owner_run_id_uq');
            $table->timestamps();
        });
        Schema::create('learning_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('run_id');
            $table->uuid('presentation_id');
            $table->foreign(['user_id', 'run_id', 'presentation_id'], 'la_presentation_owner_fk')->references(['user_id', 'run_id', 'id'])->on('learning_presentations')->cascadeOnDelete();
            $table->uuid('client_event_id');
            $table->string('event_type', 32);
            $table->unsignedInteger('sequence');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->boolean('is_first_answer')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('client_occurred_at')->nullable();
            $table->json('result')->nullable();
            $table->unique(['user_id', 'client_event_id'], 'la_client_event_uq');
            $table->unique(['run_id', 'sequence'], 'la_run_sequence_uq');
            $table->unique(['presentation_id', 'is_first_answer'], 'la_first_answer_uq');
            $table->unique(['user_id', 'run_id', 'id'], 'la_owner_run_id_uq');
            $table->unique(['user_id', 'id'], 'la_owner_id_uq');
            $table->timestamps();
        });
        Schema::create('learning_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('local_date');
            $table->uuid('qualifying_run_id');
            $table->foreign(['user_id', 'qualifying_run_id'], 'lad_run_owner_fk')->references(['user_id', 'id'])->on('learning_runs')->cascadeOnDelete();
            $table->unique(['user_id', 'local_date'], 'lad_owner_date_uq');
            $table->unique('qualifying_run_id', 'lad_run_uq');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_activity_days');
        Schema::dropIfExists('learning_attempts');
        Schema::dropIfExists('learning_presentations');
        Schema::dropIfExists('daily_assignments');
        Schema::dropIfExists('learning_runs');
        Schema::dropIfExists('learning_curriculum_enrollments');
        Schema::dropIfExists('learning_profiles');
        Schema::dropIfExists('learning_level_events');
    }
};
