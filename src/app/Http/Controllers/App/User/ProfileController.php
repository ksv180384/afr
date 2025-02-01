<?php

namespace App\Http\Controllers\App\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProfileUpdateRequest;
use App\Http\Requests\User\UploadAvatar;
use App\Models\User\Gender;
use App\Models\User\UserConfigsView;
use App\Services\App\ProverbService;
use App\Services\App\UserService;
use App\Services\App\WordService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(
        Request $request,
        WordService $wordService,
        ProverbService $proverbService,
    ): Response
    {
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();

        $genders = Gender::all();
        $userConfigsView = UserConfigsView::query()->where('alias', '!=', 'druzyam')->get();
        $user = Auth::user()->load([
            'infos',
            'rang',
            'config:user_id,day_birthday,yar_birthday,email,skype,vk,odnoklassniki,telegram,whatsapp,youtube,info,residence,sex',
            'config.email',
            'config.skype',
            'config.vk',
            'config.odnoklassniki',
            'config.telegram',
            'config.whatsapp',
            'config.youtube',
            'config.info',
            'config.residence',
            'config.sex',
        ]);

        return Inertia::render('Profile/Edit', [
            'words' => $words,
            'proverb' => $proverb,
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'genders' => $genders,
            'user' => $user,
            'userConfigsView' => $userConfigsView,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UserService $userService): RedirectResponse
    {
//        $request->user()->fill($request->validated());

//        if ($request->user()->isDirty('email')) {
//            $request->user()->email_verified_at = null;
//        }

        $validatedData = collect($request->validated());
        $userData = $validatedData->only(['name', 'birthday', 'gender_id', 'info', 'residence'])->toArray();
        $userInfoData = $validatedData->only(['odnoklassniki', 'skype', 'telegram', 'vk', 'whatsapp', 'youtube'])->toArray();
        $userConfigData = $validatedData->only([
            'view_email', 'view_info', 'view_odnoklassniki', 'view_residence', 'view_sex', 'view_skype',
            'view_telegram', 'view_vk', 'view_whatsapp', 'view_youtube', 'day_birthday', 'yar_birthday'
        ])->mapWithKeys(function ($value, $key) {
            $keyMapping = [
                'view_email' => 'email',
                'view_info' => 'info',
                'view_odnoklassniki' => 'odnoklassniki',
                'view_residence' => 'residence',
                'view_sex' => 'sex',
                'view_skype' => 'skype',
                'view_telegram' => 'telegram',
                'view_vk' => 'vk',
                'view_whatsapp' => 'whatsapp',
                'view_youtube' => 'youtube',
                'day_birthday' => 'day_birthday',
                'yar_birthday' => 'yar_birthday',
            ];

            return [$keyMapping[$key] => $value];
        })->toArray();

        $userService->update($request->user(), $userData, $userInfoData, $userConfigData);

        return Redirect::route('profile.edit');
    }

    public function uploadAvatar(UploadAvatar $request, UserService $userService)
    {
        $image = $request->file('avatar');
        $userService->uploadAvatar(Auth::user(), $image);

        return response()->json([
            'message' => 'Аватар успешно изменен.',
            'data' => [
                'avatar' => Auth::user()->avatar,
                'avatar_link' => Auth::user()->avatar_link,
            ]
        ]);
    }

    public function removeAvatar(UserService $userService)
    {
        $userService->removeAvatar(Auth::user());

        return response()->json([
            'message' => 'Аватар успешно удален.',
            'data' => [
                'avatar' => Auth::user()->avatar,
                'avatar_link' => Auth::user()->avatar_link,
            ]
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
