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
     * После создания тестовой песни добавляет соответствующую связь с исполнителем.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PlayerSongs $song): void {
            $artist = PlayerArtistsSong::query()->where('name', $song->artist_name)->first();

            if ($artist !== null) {
                $song->artists()->attach($artist->id, ['position' => 0]);
            }
        });
    }

    /**
     * Формирует значения полей для тестовой песни.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $artist = PlayerArtistsSong::inRandomOrder()->first();
        $user = User::inRandomOrder()->first();

        return [
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
