<?php

use Illuminate\Database\Seeder;

class OldToNewWordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = DB::connection('mysql2')->table('slova_part_of_speech')
            ->select('id', 'name')->get();


        DB::connection('mysql');
        foreach ($users as $user){

            \App\Models\Words\WordsPartOfSpeech::create([
                'id' => $user->id,
                'title' => $user->name,
            ]);
        }

        $words = DB::connection('mysql2')->table('slova')
            ->select('id_slova', 'slova_gl', 'slovo', 'perevod', 'transc', 'primer', 'proiznoshenie')->get();


        DB::connection('mysql');
        foreach ($words as $word){

            \App\Models\Words\Word::create([
                'id' => $word->id_slova,
                'id_part_of_speech' => $word->slova_gl?:null,
                'word' => $word->slovo,
                'translation' => $word->perevod,
                'transcription' => $word->transc,
                'example' => $word->primer,
                'pronunciation' => $word->proiznoshenie,
            ]);
        }
    }
}
