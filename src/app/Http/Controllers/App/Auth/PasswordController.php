<?php

namespace App\Http\Controllers\App\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePassword;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePassword $request): RedirectResponse
    {
        $request->user()->update([
            'password' => bcrypt(md5($request->validated(['password']))),
        ]);

        return back();
    }
}
