<?php

namespace App\Services\App;

use App\Models\User\User;

class UserConfigService
{

    public function update(User $user, array $userConfigData)
    {
        $user->config()->update($userConfigData);
    }
}
