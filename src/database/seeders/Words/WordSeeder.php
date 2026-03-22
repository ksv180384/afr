<?php

namespace Database\Seeders\Words;

use App\Models\Words\Word;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Word::factory()->count(1)->has_part_of_speech(1)->create();

        $this->command->info('Сидер WordSeeder успешно выполнен.');
    }
}
