<?php

namespace Database\Factories\Words;

use App\Models\Words\Word;
use App\Models\Words\WordsPartOfSpeech;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Words\Word>
 */
class WordFactory extends Factory
{
    protected $model = Word::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_part_of_speech' => WordsPartOfSpeech::inRandomOrder()->first()->id, // Ссылка на часть речи
            'word' => fake()->text(10),
            'translation' => fake()->text(10), // Превод
            'transcription' => fake()->text(10), // Транскрипция
            'example' => fake()->sentence(10), // Пример
        ];
    }
}
