<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Services\Learning\Validation\CanonicalJson;
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

    public function withContent(array $payload = ['fixture' => true]): static
    {
        return $this->state(['payload' => $payload, 'checksum' => CanonicalJson::hash($payload)]);
    }
}
