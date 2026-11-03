<?php

namespace Database\Factories\Learning;

use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalVocabulary>
 */
class PersonalVocabularyFactory extends Factory
{
    protected $model = PersonalVocabulary::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lexeme_key' => 'fr:fixture:'.fake()->unique()->uuid(),
            'snapshot' => ['fixture' => true],
            'first_added_at' => now('UTC'),
            'next_review_at' => now('UTC')->addDay(),
        ];
    }
}
