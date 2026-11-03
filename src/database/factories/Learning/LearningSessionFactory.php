<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningSession>
 */
class LearningSessionFactory extends Factory
{
    protected $model = LearningSession::class;

    public function definition(): array
    {
        return [
            'external_id' => 'SESSION-'.fake()->unique()->uuid(),
            'level' => LearningLevel::PreA1->value,
            'unit_id' => LearningUnit::factory(),
            'sort_order' => 1,
        ];
    }
}
