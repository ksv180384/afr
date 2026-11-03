<?php

namespace App\Console\Commands;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningRun;
use App\Models\Learning\SessionImportBatch;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PracticeMetrics extends Command
{
    protected $signature = 'daily-practice:metrics {--days=7 : UTC reporting window, 1 to 90 days}';

    protected $description = 'Aggregate practice starts, finishes, returning learners and problem steps without personal answers';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 90) {
            $this->error('--days must be between 1 and 90.');

            return self::FAILURE;
        }
        $since = CarbonImmutable::now('UTC')->subDays($days);
        $runs = LearningRun::query()->where('started_at', '>=', $since);
        $returning = LearningRun::query()->where('started_at', '>=', $since)->whereExists(function ($query): void {
            $query->selectRaw('1')->from('learning_runs as previous')->whereColumn('previous.user_id', 'learning_runs.user_id')
                ->where('previous.status', 'completed')->whereColumn('previous.local_date', '<', 'learning_runs.local_date');
        })->distinct()->count('user_id');
        $problems = LearningAttempt::query()->where('received_at', '>=', $since)
            ->where(fn ($query) => $query->whereIn('event_type', ['help', 'unknown', 'technical_skip'])->orWhere('learning_attempts.result->correct', false))
            ->join('learning_presentations', 'learning_presentations.id', '=', 'learning_attempts.presentation_id')
            ->join('learning_runs', 'learning_runs.id', '=', 'learning_attempts.run_id')
            ->select('learning_runs.session_version_id', 'learning_presentations.task_id', 'learning_attempts.event_type')
            ->selectRaw('COUNT(*) as events')->groupBy('learning_runs.session_version_id', 'learning_presentations.task_id', 'learning_attempts.event_type')
            ->orderByDesc('events')->limit(20)->get()->toArray();
        $report = ['window_days' => $days, 'timezone' => 'UTC', 'starts' => (clone $runs)->count(),
            'finishes' => LearningRun::where('finished_at', '>=', $since)->where('status', 'completed')->count(),
            'imported_starts' => (clone $runs)->whereNotNull('import_meta')->count(),
            'returning_learners' => $returning, 'problem_steps' => $problems,
            'pending_expired_previews' => SessionImportBatch::where('status', 'preview')->where('expires_at', '<=', now('UTC'))->count()];
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (config('daily_practice.operations.telemetry')) {
            \Illuminate\Support\Facades\Log::channel('daily_practice')->info('practice_metrics', $report);
        }

        return self::SUCCESS;
    }
}
