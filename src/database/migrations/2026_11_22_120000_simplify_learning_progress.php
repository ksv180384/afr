<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable();
        });
        Schema::table('learning_profiles', function (Blueprint $table): void {
            $table->dateTime('last_manual_level_changed_at')->nullable();
            $table->boolean('last_level_change_automatic')->default(false);
        });

        $manualChange = DB::table('learning_level_events')->select('occurred_at')
            ->whereColumn('learning_level_events.id', 'learning_profiles.last_manual_level_event_id');
        DB::table('learning_profiles')->update(['last_manual_level_changed_at' => DB::raw('('.$manualChange->toSql().')')]);

        $timezone = DB::table('learning_profiles')->select('timezone')->whereColumn('learning_profiles.user_id', 'users.id');
        DB::table('users')->whereIn('id', DB::table('learning_profiles')->select('user_id'))
            ->update(['timezone' => DB::raw('('.$timezone->toSql().')')]);

        $latestLevelChange = DB::table('learning_level_events')->select('source')
            ->whereColumn('learning_level_events.user_id', 'learning_profiles.user_id')
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(1);
        DB::table('learning_profiles')->whereRaw('('.$latestLevelChange->toSql().') = ?', ['automatic'])
            ->update(['last_level_change_automatic' => true]);

        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        Schema::table('guest_event_mappings', function (Blueprint $table) use ($sqlite): void {
            $table->dropForeign($sqlite ? ['owner_user_id', 'server_level_event_id'] : 'gem_level_owner_fk');
            $table->dropColumn('server_level_event_id');
        });
        Schema::table('learning_profiles', function (Blueprint $table) use ($sqlite): void {
            $table->dropForeign($sqlite ? ['user_id', 'last_manual_level_event_id'] : 'lp_manual_event_fk');
            $table->dropColumn(['timezone', 'last_manual_level_event_id']);
        });

        Schema::dropIfExists('learning_editorial_events');
        Schema::dropIfExists('learning_objective_evidence');
        Schema::dropIfExists('learning_curriculum_enrollments');
        Schema::dropIfExists('learning_curriculum_objectives');
        Schema::dropIfExists('learning_curriculum_checkpoints');
        Schema::dropIfExists('learning_level_events');
    }

    public function down(): void
    {
        throw new LogicException('Deleted learning audit records cannot be restored by a schema rollback.');
    }
};
