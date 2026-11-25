<?php

namespace Database\Factories\Learning;

use App\Models\Learning\LearningSession;
use App\Models\Learning\PersonalVocabulary;
use App\Models\Learning\PracticeLexeme;
use App\Models\Learning\SessionLexeme;
use App\Models\User\User;
use App\Services\Learning\VocabularyStudySets;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<PersonalVocabulary>
 */
class PersonalVocabularyFactory extends Factory
{
    protected $model = PersonalVocabulary::class;

    public function configure(): static
    {
        return $this->afterMaking(fn (PersonalVocabulary $word) => PracticeLexeme::firstOrCreate(
            ['lexeme_key' => $word->lexeme_key], ['content' => []]));
    }

    public function studied(array $expression, ?LearningSession $lesson = null): static
    {
        $expression += ['lexeme_key' => 'fr:fixture:'.fake()->unique()->uuid(), 'id' => 'fixture'];
        $lesson ??= LearningSession::factory()->withContent(['vocabulary' => [$expression]])->create();

        return $this->state(['lexeme_key' => $expression['lexeme_key']])->afterCreating(
            fn (PersonalVocabulary $word) => self::addSource($word, $lesson));
    }

    public static function addSource(PersonalVocabulary $word, ?LearningSession $lesson = null): void
    {
        $lesson ??= LearningSession::factory()->withContent()->create();
        DB::transaction(function () use ($word, $lesson) {
            $entry = SessionLexeme::firstOrCreate(['session_id' => $lesson->id, 'lexeme_key' => $word->lexeme_key],
                ['context' => ['id' => 'fixture']]);
            DB::table('learning_vocabulary_legacy_studies')->insert([
                'user_id' => $word->user_id, 'set_id' => VocabularyStudySets::forEntries($lesson->id, [$entry->id]),
                'first_added_at' => now('UTC'),
            ]);
        });
    }

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lexeme_key' => 'fr:fixture:'.fake()->unique()->uuid(),
            'first_added_at' => now('UTC'),
            'next_review_at' => now('UTC')->addDay(),
        ];
    }
}
