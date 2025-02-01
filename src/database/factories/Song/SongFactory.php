<?php

namespace Database\Factories\Song;

use App\Models\Player\PlayerArtistsSong;
use App\Models\Player\PlayerSongs;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class SongFactory extends Factory
{
    protected $model = PlayerSongs::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $artist = PlayerArtistsSong::inRandomOrder()->first();
        $user = User::inRandomOrder()->first();

        return [
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'title' => fake()->text(10),
            'text_fr' => fake()->sentence(20),
            'text_ru' => fake()->sentence(20),
            'text_transcription' => fake()->sentence(20),
            'user_id' => $user->id,
            'hidden' => false,
        ];
    }
}
