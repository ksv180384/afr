<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $identifierCollation = Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin';

        Schema::create('guest_sync_episodes', function (Blueprint $table) use ($identifierCollation) {
            $table->uuid('id')->primary();
            $table->uuid('guest_id')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('baseline_profile_revision')->nullable();
            $table->string('baseline_current_level', 8)->collation($identifierCollation)->nullable();
            $table->foreign('baseline_current_level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->json('accepted_level_event_ids')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->unique(['id', 'guest_id', 'owner_user_id'], 'gse_identity_uq');
            $table->timestamps();
        });
        Schema::create('guest_sync_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('episode_id');
            $table->uuid('guest_id');
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreign(['episode_id', 'guest_id', 'owner_user_id'], 'gsb_episode_owner_fk')->references(['id', 'guest_id', 'owner_user_id'])->on('guest_sync_episodes')->cascadeOnDelete();
            $table->uuid('batch_id');
            $table->char('payload_hash', 64);
            $table->string('status', 24)->default('pending');
            $table->json('accepted')->nullable();
            $table->json('rejected')->nullable();
            $table->json('receipt')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('completed_at')->nullable();
            $table->unique(['guest_id', 'batch_id'], 'gsb_guest_batch_uq');
            $table->timestamps();
        });
        Schema::create('guest_event_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('episode_id');
            $table->uuid('guest_id');
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreign(['episode_id', 'guest_id', 'owner_user_id'], 'gem_episode_owner_fk')->references(['id', 'guest_id', 'owner_user_id'])->on('guest_sync_episodes')->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->uuid('guest_entity_id');
            $table->uuid('server_run_id')->nullable();
            $table->foreign(['owner_user_id', 'server_run_id'], 'gem_run_owner_fk')->references(['user_id', 'id'])->on('learning_runs')->cascadeOnDelete();
            $table->uuid('server_event_id')->nullable();
            $table->foreign(['owner_user_id', 'server_event_id'], 'gem_event_owner_fk')->references(['user_id', 'id'])->on('learning_attempts')->cascadeOnDelete();
            $table->uuid('server_level_event_id')->nullable();
            $table->foreign(['owner_user_id', 'server_level_event_id'], 'gem_level_owner_fk')->references(['user_id', 'id'])->on('learning_level_events')->cascadeOnDelete();
            $table->json('receipt')->nullable();
            $table->unique(['guest_id', 'entity_type', 'guest_entity_id'], 'gem_guest_entity_uq');
            $table->timestamps();
        });
        Schema::create('learning_content_reports', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->foreignId('session_version_id')->constrained('learning_session_versions')->restrictOnDelete();
            $table->string('task_id', 128)->collation($identifierCollation)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('text');
            $table->string('status', 24)->default('open');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('editor_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->index(['status', 'created_at'], 'lrep_status_idx');
            $table->timestamps();
        });
        Schema::create('learning_url_aliases', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('old_path', 512)->unique();
            $table->string('target_path', 512);
            $table->foreignId('session_id')->nullable()->constrained('learning_sessions')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('learning_units')->restrictOnDelete();
            $table->string('level_code', 8)->collation($identifierCollation)->nullable();
            $table->foreign('level_code')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('learning_editorial_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('session_version_id')->nullable()->constrained('learning_session_versions')->restrictOnDelete();
            $table->foreignId('curriculum_id')->nullable()->constrained('learning_curricula')->restrictOnDelete();
            $table->foreignId('catalog_revision_id')->nullable()->constrained('learning_catalog_revisions')->restrictOnDelete();
            $table->uuid('media_id')->nullable();
            $table->foreign('media_id')->references('id')->on('learning_media')->restrictOnDelete();
            $table->uuid('import_batch_id')->nullable();
            $table->foreign('import_batch_id')->references('id')->on('session_import_batches')->restrictOnDelete();
            $table->string('operation', 32);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->dateTime('occurred_at');
            $table->index(['session_version_id', 'occurred_at'], 'lee_version_idx');
            $table->index(['actor_id', 'occurred_at'], 'lee_actor_idx');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_editorial_events');
        Schema::dropIfExists('learning_url_aliases');
        Schema::dropIfExists('learning_content_reports');
        Schema::dropIfExists('guest_event_mappings');
        Schema::dropIfExists('guest_sync_batches');
        Schema::dropIfExists('guest_sync_episodes');
    }
};
