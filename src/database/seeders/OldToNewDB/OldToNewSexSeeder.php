<?php

use Illuminate\Database\Seeder;

class OldToNewSexSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('sex')->insert([
            ['title' => 'Мужской',],
            ['title' => 'Женский',],
        ]);
    }
}
