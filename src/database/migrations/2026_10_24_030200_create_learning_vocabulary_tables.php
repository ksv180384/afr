<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $identifierCollation = Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin';

        Schema::create('personal_vocabulary', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('lexeme_key', 128)->collation($identifierCollation);
            $table->json('snapshot');
            $table->boolean('needs_review')->default(false);
            $table->unsignedInteger('streak')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedInteger('review_step')->default(0);
            $table->dateTime('first_added_at');
            $table->dateTime('next_review_at');
            $table->dateTime('last_reviewed_at')->nullable();
            $table->unique(['user_id', 'lexeme_key'], 'pv_owner_lexeme_uq');
            $table->unique(['user_id', 'id'], 'pv_owner_id_uq');
            $table->unique(['user_id', 'id', 'lexeme_key'], 'pv_owner_id_lexeme_uq');
            $table->index(['user_id', 'needs_review', 'next_review_at'], 'pv_review_idx');
            $table->timestamps();
        });
        Schema::create('personal_vocabulary_sources', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('personal_word_id');
            $table->foreign(['user_id', 'personal_word_id'], 'pvs_word_owner_fk')->references(['user_id', 'id'])->on('personal_vocabulary')->cascadeOnDelete();
            $table->foreignId('session_version_id')->constrained('learning_session_versions')->restrictOnDelete();
            $table->string('vocabulary_id', 128)->collation($identifierCollation);
            $table->json('snapshot');
            $table->dateTime('first_added_at');
            $table->unique(['personal_word_id', 'session_version_id'], 'pvs_source_uq');
            $table->timestamps();
        });
        Schema::create('vocabulary_event_effects', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('event_id');
            $table->foreign(['user_id', 'event_id'], 'vee_event_owner_fk')->references(['user_id', 'id'])->on('learning_attempts')->cascadeOnDelete();
            $table->unsignedBigInteger('personal_word_id');
            $table->string('lexeme_key', 128)->collation($identifierCollation);
            $table->foreign(['user_id', 'personal_word_id', 'lexeme_key'], 'vee_word_owner_fk')->references(['user_id', 'id', 'lexeme_key'])->on('personal_vocabulary')->cascadeOnDelete();
            $table->json('effect');
            $table->dateTime('applied_at');
            $table->unique(['event_id', 'lexeme_key'], 'vee_event_lexeme_uq');
            $table->timestamps();
        });
        Schema::create('vocabulary_run_effects', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('lexeme_key', 128)->collation($identifierCollation);
            $table->foreign(['user_id', 'lexeme_key'], 'vre_word_owner_fk')->references(['user_id', 'lexeme_key'])->on('personal_vocabulary')->cascadeOnDelete();
            $table->uuid('run_id');
            $table->foreign(['user_id', 'run_id'], 'vre_run_owner_fk')->references(['user_id', 'id'])->on('learning_runs')->cascadeOnDelete();
            $table->boolean('success_applied')->default(false);
            $table->dateTime('applied_at')->nullable();
            $table->unique(['user_id', 'lexeme_key', 'run_id'], 'vre_run_lexeme_uq');
            $table->timestamps();
        });
        Schema::create('learning_objective_evidence', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->foreign(['user_id', 'enrollment_id'], 'loe_enrollment_owner_fk')->references(['user_id', 'id'])->on('learning_curriculum_enrollments')->cascadeOnDelete();
            $table->foreignId('objective_id')->constrained('learning_objectives')->restrictOnDelete();
            $table->uuid('event_id');
            $table->uuid('run_id');
            $table->foreign(['user_id', 'run_id', 'event_id'], 'loe_event_owner_fk')->references(['user_id', 'run_id', 'id'])->on('learning_attempts')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('learning_sessions')->restrictOnDelete();
            $table->unsignedBigInteger('session_version_id');
            $table->foreign(['session_id', 'session_version_id'], 'loe_session_version_fk')->references(['session_id', 'id'])->on('learning_session_versions')->restrictOnDelete();
            $table->foreign(['run_id', 'session_version_id'], 'loe_run_version_fk')->references(['id', 'session_version_id'])->on('learning_runs')->cascadeOnDelete();
            $table->string('task_id', 128)->collation($identifierCollation);
            $table->string('skill', 32);
            $table->boolean('eligible')->default(false);
            $table->dateTime('occurred_at');
            $table->unique(['event_id', 'enrollment_id', 'objective_id'], 'loe_event_goal_uq');
            $table->index(['user_id', 'enrollment_id', 'objective_id', 'eligible'], 'loe_progress_idx');
            $table->index(['session_id', 'task_id'], 'loe_stable_task_idx');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_objective_evidence');
        Schema::dropIfExists('vocabulary_run_effects');
        Schema::dropIfExists('vocabulary_event_effects');
        Schema::dropIfExists('personal_vocabulary_sources');
        Schema::dropIfExists('personal_vocabulary');
    }
};
