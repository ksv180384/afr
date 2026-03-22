<?php

use Illuminate\Database\Seeder;

class OldToNewPlayerSongSearchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $songs = DB::connection('mysql2')->table('pleer')
            ->select('id_pl', 'id_pl_parent', 'name_artist', 'name_song', 'name_file', 'text_song_fr',
                'text_song_ru', 'text_song_tr', 'date', 'autor', 'vue_flag')->get();


        DB::connection('mysql');
        foreach ($songs as $song){

            if($song->vue_flag == 1){
                continue;
            }

            \App\Models\Player\PlayerSearchSong::create([
                'artist' => $song->name_artist,
                'title' => $song->name_song,
                'title_file' => $song->name_file,
                'created_at' => $song->date,
                'updated_at' => null,
            ]);
        }
    }
}
