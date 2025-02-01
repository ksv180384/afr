<?php

namespace App\Services\App;

use App\Models\User\Online;

class StatisticService {

    /**
     * Получает писок авторизованных пользователей находящихся сейчас на сайте
     * @return mixed
     */
    public function onlineUsers(){
        $usersOnline = Online::with(['user:id,login,email'])
            ->get(['user_id']);

        return $usersOnline;
    }
}
