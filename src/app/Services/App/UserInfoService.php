<?php

namespace App\Services\App;

use App\Models\User\User;

class UserInfoService
{
    public function update(User $user, array $userConfigData)
    {
        $user->infos()->update($userConfigData);
    }
}
