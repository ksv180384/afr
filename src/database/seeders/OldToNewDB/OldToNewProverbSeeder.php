<?php

use Illuminate\Database\Seeder;

class OldToNewProverbSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $proverbs = DB::connection('mysql2')->table('poslovica')
            ->select('id_posl', 'posl', 'per_posl')->get();


        DB::connection('mysql');
        foreach ($proverbs as $proverb){


            App\Models\Proverb::create([
                'id' => $proverb->id_posl,
                'text' => $proverb->posl,
                'translation' => $proverb->per_posl,
            ]);
        }
    }
}
