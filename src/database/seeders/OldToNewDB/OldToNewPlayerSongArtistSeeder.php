<?php

use Illuminate\Database\Seeder;

class OldToNewPlayerSongArtistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $proverbs = DB::connection('mysql2')->table('pleer_name_artist')
            ->select('id_mame_artist', 'name_artist')->where('vue_flag', '=', 1)->get();


        DB::connection('mysql');
        foreach ($proverbs as $proverb){


            \App\Models\Player\PlayerArtistsSong::create([
                'id' => $proverb->id_mame_artist,
                'name' => $proverb->name_artist,
            ]);
        }
    }
}
