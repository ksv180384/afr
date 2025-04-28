<?php

namespace Database\Factories\Words;

use App\Models\Words\WordsPartOfSpeech;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Words\WordsPartOfSpeech>
 */
class WordsPartOfSpeechFactory extends Factory
{
    protected $model = WordsPartOfSpeech::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->text(10),
        ];
    }
}
