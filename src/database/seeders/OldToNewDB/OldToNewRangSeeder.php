<?php

use Illuminate\Database\Seeder;

class OldToNewRangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('rangs')->insert([
            ['title' => 'Пользователь', 'alias' => \Illuminate\Support\Str::slug('Пользователь')],
            ['title' => 'Модератор', 'alias' => \Illuminate\Support\Str::slug('Модератор')],
            ['title' => 'Администратор', 'alias' => \Illuminate\Support\Str::slug('Администратор')],
            ['title' => 'Забанен', 'alias' => \Illuminate\Support\Str::slug('Забанен')],
        ]);
    }
}
