<?php

use App\Enums\Learning\RunStatus;
use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(compact: true);
    }

    public function down(): void
    {
        $this->rewrite(compact: false);
    }

    private function rewrite(bool $compact): void
    {
        foreach (LearningRun::has('attempts')->select('id')->lazyById(250) as $candidate) {
            DB::transaction(function () use ($candidate, $compact) {
                // Serialize the rewrite with answers, completion and cancellation of this run.
                $run = LearningRun::lockForUpdate()->find($candidate->id, ['id', 'status', 'checking_policy_version', 'import_meta']);
                if (! $run) {
                    return;
                }
                $archived = $run->status === RunStatus::Completed && $run->import_meta !== null;
                $attempts = $run->attempts()->join('learning_presentations as p', 'p.id', '=', 'learning_attempts.presentation_id')
                    ->select('learning_attempts.*', 'p.task_id');
                foreach ($attempts->lazyById(250, 'learning_attempts.id', 'id') as $attempt) {
                    if ($compact) {
                        $attempt->compactDetails($run->checking_policy_version, $attempt->task_id, $archived);
                    } elseif (($attempt->result['_v'] ?? null) === 1) {
                        $attempt->result = $attempt->gradingResult($run->checking_policy_version, $attempt->task_id);
                        if (! $archived || $attempt->payload !== []) {
                            $attempt->payload = $attempt->requestEnvelope($attempt->result['run_revision']) + $attempt->payload;
                        }
                    }
                    if ($changes = $attempt->getDirty()) {
                        DB::table('learning_attempts')->where('id', $attempt->id)->update($changes);
                    }
                }
                if ($compact && $run->status === RunStatus::Cancelled) {
                    LearningAttempt::trimClosedRun($run);
                }
            }, 3);
        }
    }
};
