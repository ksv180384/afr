<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\SessionVersionStatus;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningSessionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningSessionVersion>
 */
class LearningSessionVersionFactory extends Factory
{
    protected $model = LearningSessionVersion::class;

    public function definition(): array
    {
        return [
            'session_id' => LearningSession::factory(),
            'version' => 1,
            'schema_version' => 1,
            'payload' => ['fixture' => true],
            'checksum' => hash('sha256', '{"fixture":true}'),
            'status' => SessionVersionStatus::Draft->value,
        ];
    }
}
