<?php

use Illuminate\Database\Seeder;

class OldToNewGrammarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $grammars = DB::connection('mysql2')->table('gram')
            ->select('description', 'gram_naz', 'gram_text', 'gram_istchnik')->get();

        DB::connection('mysql');
        foreach ($grammars as $grammar){

            \App\Models\Grammar::create([
                'title' => $grammar->gram_naz,
                'description' => $grammar->description,
                'content' => $grammar->gram_text,
                'source' => $grammar->gram_istchnik,
            ]);

        }
    }
}
