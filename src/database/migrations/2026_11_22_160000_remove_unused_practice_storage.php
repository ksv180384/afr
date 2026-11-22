<?php

use App\Services\Learning\CheckingPolicy;
use App\Services\Learning\Validation\CanonicalJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('learning_runs')->whereIn('status', ['in_progress', 'paused'])
            ->select('checking_policy_version', 'checking_policy_snapshot')->distinct()->cursor() as $run) {
            $policy = CheckingPolicy::forVersion((int) $run->checking_policy_version);
            $snapshot = json_decode($run->checking_policy_snapshot, true, flags: JSON_THROW_ON_ERROR);
            if (CanonicalJson::hash($snapshot) !== CanonicalJson::hash($policy->rules)) {
                throw new LogicException('An unfinished run has checking rules that differ from its registered policy.');
            }
        }

        Schema::drop('learning_activity_days');
        Schema::table('learning_runs', fn (Blueprint $table) => $table->dropColumn('checking_policy_snapshot'));
        Schema::table('learning_presentations', fn (Blueprint $table) => $table->dropColumn('choice_order'));
        Schema::table('learning_objectives', fn (Blueprint $table) => $table->dropColumn('definition'));
        Schema::table('learning_curricula', fn (Blueprint $table) => $table->dropColumn(['promotion_policy_version', 'promotion_policy_snapshot']));
        Schema::table('personal_vocabulary_sources', fn (Blueprint $table) => $table->dropColumn('vocabulary_id'));
        Schema::table('guest_sync_batches', fn (Blueprint $table) => $table->dropColumn(['accepted', 'rejected']));
        Schema::table('guest_sync_episodes', function (Blueprint $table): void {
            $table->dropForeign(['baseline_current_level']);
            $table->dropColumn(['baseline_current_level', 'baseline_profile_revision', 'baseline_snapshot', 'accepted_level_event_ids']);
        });
    }

    public function down(): void
    {
        throw new LogicException('The discarded practice data cannot be reconstructed by a schema rollback.');
    }
};
