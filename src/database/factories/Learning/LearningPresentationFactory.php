<?php

namespace Database\Factories\Learning;

use App\Models\Learning\LearningPresentation;
use App\Models\Learning\LearningRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningPresentation>
 */
class LearningPresentationFactory extends Factory
{
    protected $model = LearningPresentation::class;

    public function definition(): array
    {
        return [
            'run_id' => LearningRun::factory(),
            'user_id' => fn (array $attributes): int => LearningRun::query()->findOrFail($attributes['run_id'])->user_id,
            'task_id' => 'Q01',
            'position' => 1,
            'snapshot' => ['fixture' => true],
            'choice_order' => ['a', 'b'],
            'status' => 'pending',
        ];
    }
}
