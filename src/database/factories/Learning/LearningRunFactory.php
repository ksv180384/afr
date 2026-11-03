<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\RunStatus;
use App\Models\Learning\LearningRun;
use App\Models\Learning\LearningSessionVersion;
use App\Models\User\User;
use App\Services\Learning\CheckingPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningRun>
 */
class LearningRunFactory extends Factory
{
    protected $model = LearningRun::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_version_id' => LearningSessionVersion::factory(),
            'kind' => 'daily',
            'status' => RunStatus::InProgress->value,
            'snapshot' => fn (array $attributes): array => LearningSessionVersion::query()->findOrFail($attributes['session_version_id'])->payload,
            'checking_policy_version' => CheckingPolicy::CURRENT_VERSION,
            'checking_policy_snapshot' => CheckingPolicy::forVersion(CheckingPolicy::CURRENT_VERSION)->rules,
            'timezone' => 'UTC',
            'local_date' => now('UTC')->toDateString(),
            'started_at' => now('UTC'),
            'revision' => 1,
        ];
    }
}
