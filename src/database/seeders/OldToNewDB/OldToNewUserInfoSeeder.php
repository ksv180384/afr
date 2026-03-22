<?php

use Illuminate\Database\Seeder;

class OldToNewUserInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users_info = DB::connection('mysql2')->table('users_contact')
            ->select('id_user', 'facebook', 'icq', 'skype', 'twitter', 'vc')->get();


        DB::connection('mysql');
        foreach ($users_info as $item) {

            \App\Models\User\UserInfo::create([
                'user_id' => $item->id_user?:null,
                'facebook' => $item->facebook?:null,
                'skype' => $item->skype?:null,
                'twitter' => $item->twitter?:null,
                'vk' => $item->vc?:null,
                'odnoklassniki' => null,
                'telegram' => null,
                'whatsapp' => null,
                'viber' => null,
                'youtube' => null,
                'instagram' => null,
            ]);
        }
    }
}
