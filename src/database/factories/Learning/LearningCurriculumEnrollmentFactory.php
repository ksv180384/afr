<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningCurriculum;
use App\Models\Learning\LearningCurriculumEnrollment;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningCurriculumEnrollment>
 */
class LearningCurriculumEnrollmentFactory extends Factory
{
    protected $model = LearningCurriculumEnrollment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'level' => LearningLevel::PreA1->value,
            'curriculum_version_id' => LearningCurriculum::factory(),
            'promotion_policy_version' => 1,
            'promotion_policy_snapshot' => config('daily_practice.promotion.policies.1'),
            'enrolled_at' => now('UTC'),
        ];
    }
}
