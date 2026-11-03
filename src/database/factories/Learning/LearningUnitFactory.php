<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningUnit>
 */
class LearningUnitFactory extends Factory
{
    protected $model = LearningUnit::class;

    public function definition(): array
    {
        return [
            'external_id' => 'UNIT-'.fake()->unique()->uuid(),
            'level' => LearningLevel::PreA1->value,
            'sort_order' => fake()->unique()->numberBetween(1, 60000),
            'slug' => 'unit-'.fake()->unique()->uuid(),
            'title' => 'Тестовая тема',
        ];
    }
}
