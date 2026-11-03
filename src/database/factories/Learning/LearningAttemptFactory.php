<?php

namespace Database\Factories\Learning;

use App\Models\Learning\LearningAttempt;
use App\Models\Learning\LearningPresentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningAttempt>
 */
class LearningAttemptFactory extends Factory
{
    protected $model = LearningAttempt::class;

    public function definition(): array
    {
        return [
            'presentation_id' => LearningPresentation::factory(),
            'run_id' => fn (array $attributes): string => LearningPresentation::query()->findOrFail($attributes['presentation_id'])->run_id,
            'user_id' => fn (array $attributes): int => LearningPresentation::query()->findOrFail($attributes['presentation_id'])->user_id,
            'client_event_id' => fake()->unique()->uuid(),
            'event_type' => 'answer',
            'sequence' => 1,
            'payload' => ['choice_id' => 'a'],
            'payload_hash' => hash('sha256', '{"choice_id":"a"}'),
            'is_first_answer' => true,
            'received_at' => now('UTC'),
        ];
    }
}
