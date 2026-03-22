<?php

use Illuminate\Database\Seeder;

class OldToNewUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $users = DB::connection('mysql2')->table('users')
                    ->select('id', 'login', 'email', 'password', 'avatar', 'pol', 'o_sebe',
                    'chislo_r', 'mois', 'ans', 'podpis', 'rang', 'data_reg')->get();

        //$users = $users->unique('email');
        DB::connection('mysql');
        foreach ($users as $user){
            if($user->pol == 'Женский'){
                $pol = 2;
            }elseif($user->pol == 'Мужской'){
                $pol = 1;
            }else{
                $pol = null;
            }


            \App\Models\User\User::create([
               'id' => $user->id,
               'login' => $user->login,
               'email' => $user->email?:null,
               'email_verified_at' => \Carbon\Carbon::now(),
               'password' => bcrypt($user->password),
               'avatar' => !empty($user->avatar) ? 'uploads/users/' . $user->id . '/' . $user->avatar : null,
               'sex' => $pol,
               'info' => $user->o_sebe,
               'rang' => $user->rang,
               'created_at' => $user->data_reg,
               'updated_at' => \Carbon\Carbon::now(),
            ]);
        }

    }
}
