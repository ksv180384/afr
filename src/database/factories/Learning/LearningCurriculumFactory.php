<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\LearningLevel;
use App\Enums\Learning\SessionVersionStatus;
use App\Models\Learning\LearningCurriculum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningCurriculum>
 */
class LearningCurriculumFactory extends Factory
{
    protected $model = LearningCurriculum::class;

    public function definition(): array
    {
        return [
            'external_id' => 'CURRICULUM-'.fake()->unique()->uuid(),
            'version' => 1,
            'level' => LearningLevel::PreA1->value,
            'required_objective_ids' => [],
            'checkpoint_session_ids' => [],
            'promotion_policy_version' => 1,
            'promotion_policy_snapshot' => config('daily_practice.promotion.policies.1'),
            'checksum' => hash('sha256', 'test-curriculum'),
            'status' => SessionVersionStatus::Draft->value,
        ];
    }
}
