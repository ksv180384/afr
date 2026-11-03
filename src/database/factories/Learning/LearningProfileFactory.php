<?php

namespace Database\Factories\Learning;

use App\Enums\Learning\LearningLevel;
use App\Models\Learning\LearningProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningProfile>
 */
class LearningProfileFactory extends Factory
{
    protected $model = LearningProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'current_level' => LearningLevel::PreA1->value,
            'timezone' => 'UTC',
            'revision' => 1,
        ];
    }
}
