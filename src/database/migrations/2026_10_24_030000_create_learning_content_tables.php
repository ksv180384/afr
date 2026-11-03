<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $identifierCollation = Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin';

        Schema::create('learning_levels', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('code', 8)->collation($identifierCollation)->unique();
            $table->unsignedTinyInteger('sort_order')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('pronunciation_notes')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('learning_units', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('external_id', 128)->collation($identifierCollation)->unique();
            $table->string('level', 8)->collation($identifierCollation);
            $table->foreign('level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->string('slug', 128);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->json('pronunciation_notes')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->unique(['level', 'slug'], 'lu_level_slug_uq');
            $table->unique(['level', 'sort_order'], 'lu_level_order_uq');
            $table->unique(['id', 'level'], 'lu_id_level_uq');
            $table->timestamps();
        });
        Schema::create('session_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('file_hash', 64);
            $table->char('preview_token_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('private_path', 512);
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size');
            $table->string('status', 24)->default('preview');
            $table->json('validation_report')->nullable();
            $table->json('receipt')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('committed_at')->nullable();
            $table->index(['status', 'expires_at'], 'sib_expiry_idx');
            $table->timestamps();
        });
        Schema::create('learning_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->char('checksum', 64);
            $table->string('review_status', 24)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->unique(['disk', 'path'], 'lm_storage_uq');
            $table->timestamps();
        });
        Schema::create('learning_catalog_revisions', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('level_code', 8)->collation($identifierCollation)->nullable();
            $table->foreign('level_code')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('learning_units')->restrictOnDelete();
            $table->uuid('import_batch_id')->nullable();
            $table->foreign('import_batch_id')->references('id')->on('session_import_batches')->restrictOnDelete();
            $table->json('content');
            $table->unsignedBigInteger('base_revision');
            $table->string('status', 24)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->index(['status', 'created_at'], 'lcr_status_idx');
            $table->index(['level_code', 'base_revision'], 'lcr_level_idx');
            $table->index(['unit_id', 'base_revision'], 'lcr_unit_idx');
            $table->timestamps();
        });
        Schema::create('learning_sessions', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('external_id', 128)->collation($identifierCollation)->unique();
            $table->string('level', 8)->collation($identifierCollation);
            $table->foreign('level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->unsignedBigInteger('unit_id');
            $table->foreign(['unit_id', 'level'], 'ls_unit_level_fk')->references(['id', 'level'])->on('learning_units')->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->string('slug', 128)->nullable();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->unique(['unit_id', 'slug'], 'ls_unit_slug_uq');
            $table->index(['level', 'unit_id', 'sort_order'], 'ls_catalog_idx');
            $table->timestamps();
        });
        Schema::create('learning_session_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('learning_sessions')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('payload');
            $table->char('checksum', 64);
            $table->string('status', 16)->default('draft');
            $table->uuid('import_batch_id')->nullable();
            $table->foreign('import_batch_id')->references('id')->on('session_import_batches')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('editor_notes')->nullable();
            $table->json('review_checks')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->unique(['session_id', 'version'], 'lsv_session_version_uq');
            $table->unique(['session_id', 'id'], 'lsv_session_id_uq');
            $table->index(['status', 'session_id'], 'lsv_status_idx');
            $table->timestamps();
        });
        Schema::table('learning_sessions', function (Blueprint $table) {
            $table->foreign(['id', 'active_version_id'])->references(['session_id', 'id'])->on('learning_session_versions')->restrictOnDelete();
        });
        Schema::create('learning_session_version_media', function (Blueprint $table) {
            $table->unsignedBigInteger('session_version_id');
            $table->foreign('session_version_id', 'lsvm_version_fk')->references('id')->on('learning_session_versions')->restrictOnDelete();
            $table->uuid('media_id');
            $table->foreign('media_id', 'lsvm_media_fk')->references('id')->on('learning_media')->restrictOnDelete();
            $table->primary(['session_version_id', 'media_id'], 'lsvm_pk');
        });
        Schema::create('learning_objectives', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('stable_id', 128)->collation($identifierCollation)->unique();
            $table->string('level', 8)->collation($identifierCollation);
            $table->foreign('level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->string('title');
            $table->text('definition')->nullable();
            $table->timestamps();
        });
        Schema::create('learning_curricula', function (Blueprint $table) use ($identifierCollation) {
            $table->id();
            $table->string('external_id', 128)->collation($identifierCollation);
            $table->unsignedInteger('version');
            $table->string('level', 8)->collation($identifierCollation);
            $table->foreign('level')->references('code')->on('learning_levels')->restrictOnDelete();
            $table->json('required_objective_ids');
            $table->json('checkpoint_session_ids');
            $table->unsignedInteger('promotion_policy_version')->default(1);
            $table->json('promotion_policy_snapshot');
            $table->char('checksum', 64);
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->unique(['external_id', 'version'], 'lc_external_version_uq');
            $table->unique(['id', 'level'], 'lc_id_level_uq');
            $table->index(['level', 'status'], 'lc_level_status_idx');
            $table->timestamps();
        });
        Schema::create('learning_curriculum_objectives', function (Blueprint $table) {
            $table->foreignId('curriculum_id')->constrained('learning_curricula')->restrictOnDelete();
            $table->foreignId('objective_id')->constrained('learning_objectives')->restrictOnDelete();
            $table->primary(['curriculum_id', 'objective_id'], 'lco_pk');
        });
        Schema::create('learning_curriculum_checkpoints', function (Blueprint $table) {
            $table->foreignId('curriculum_id')->constrained('learning_curricula')->restrictOnDelete();
            $table->foreignId('session_id')->constrained('learning_sessions')->restrictOnDelete();
            $table->primary(['curriculum_id', 'session_id'], 'lcc_pk');
        });
    }

    public function down(): void
    {
        Schema::table('learning_sessions', function (Blueprint $table) {
            $table->dropForeign(['id', 'active_version_id']);
        });
        Schema::dropIfExists('learning_curriculum_checkpoints');
        Schema::dropIfExists('learning_curriculum_objectives');
        Schema::dropIfExists('learning_curricula');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('learning_session_version_media');
        Schema::dropIfExists('learning_session_versions');
        Schema::dropIfExists('learning_sessions');
        Schema::dropIfExists('learning_catalog_revisions');
        Schema::dropIfExists('learning_media');
        Schema::dropIfExists('session_import_batches');
        Schema::dropIfExists('learning_units');
        Schema::dropIfExists('learning_levels');
    }
};
