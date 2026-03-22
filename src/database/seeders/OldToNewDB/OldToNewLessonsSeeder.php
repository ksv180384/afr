<?php

use Illuminate\Database\Seeder;

class OldToNewLessonsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $lessons = DB::connection('mysql2')->table('lecons')
            ->select('less_description', 'name_les', 'content_les')->get();

        DB::connection('mysql');
        foreach ($lessons as $lesson){

            \App\Models\Lesson::create([
                'title' => $lesson->name_les,
                'description' => $lesson->less_description,
                'content' => $lesson->content_les,
            ]);

        }
    }
}
