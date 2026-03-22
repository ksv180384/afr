<?php

use Illuminate\Database\Seeder;

class OldToNewUserConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('user_configs_view')->insert([
            ['title' => 'Не показывать никому', 'alias' => Str::slug('не показывать никому')],
            ['title' => 'Зарегистрированным', 'alias' => Str::slug('зарегистрированным')],
            ['title' => 'Друзьям', 'alias' => Str::slug('друзьям')],
            ['title' => 'Всем', 'alias' => Str::slug('всем')],
        ]);

        //
        $users_configs = DB::connection('mysql2')->table('users_conf')
            ->select('conf_id_user', 'conf_jour_naissance', 'conf_annee_naissance')->get();


        DB::connection('mysql');
        foreach ($users_configs as $item){

            \App\Models\User\UserConfig::create([
                'user_id' => $item->conf_id_user,
                'day_birthday' => $item->conf_jour_naissance == 'on' ? true : false,
                'yar_birthday' => $item->conf_annee_naissance == 'on' ? true : false,
                'email' => 1,
                'facebook' => 1,
                'skype' => 1,
                'twitter' => 1,
                'vk'=> 1,
                'odnoklassniki' => 1,
                'telegram' => 1,
                'whatsapp' => 1,
                'viber' => 1,
                'instagram' => 1,
                'youtube' => 1,
                'info' => 1,
                'residence' => 1,
                'sex' => 1,
            ]);
        }
    }
}
