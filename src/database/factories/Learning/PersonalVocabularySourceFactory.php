<?php

namespace Database\Factories\Learning;

use App\Models\Learning\LearningSessionVersion;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PersonalVocabularySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalVocabularySource>
 */
class PersonalVocabularySourceFactory extends Factory
{
    protected $model = PersonalVocabularySource::class;

    public function definition(): array
    {
        return [
            'personal_word_id' => PersonalVocabulary::factory(),
            'user_id' => fn (array $attributes): int => PersonalVocabulary::query()->findOrFail($attributes['personal_word_id'])->user_id,
            'session_version_id' => LearningSessionVersion::factory(),
            'vocabulary_id' => 'bonjour',
            'snapshot' => ['fixture' => true],
            'first_added_at' => now('UTC'),
        ];
    }
}
