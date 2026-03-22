<?php

use Illuminate\Database\Seeder;

class OldToNewPlayerSongSeeder extends Seeder
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

            if($song->vue_flag == 0){
                continue;
            }

            \App\Models\Player\PlayerSongs::create([
                'id' => $song->id_pl,
                'artist_id' => $song->id_pl_parent != 0 ? $song->id_pl_parent : null,
                'artist_name' => $song->name_artist,
                'title' => $song->name_song,
                'text_fr' => $song->text_song_fr,
                'text_ru' => $song->text_song_ru,
                'text_transcription' => $song->text_song_tr,
                'user_id' => $song->autor != 0 ? $song->autor : null,
                'hidden' => false,
                'created_at' => $song->date,
                'updated_at' => null,
            ]);
        }
    }
}
