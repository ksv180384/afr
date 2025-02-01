<?php

namespace App\Services\App;

use App\Models\User\User;
use App\Models\User\UserConfigsView;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserService {

    public function getUsersPagination($paginate)
    {
        $users = User::query()
            ->select([
                'id',
                'name',
                'rang_id',
                'avatar',
            ])
            ->with(['gender', 'rang'])
            ->paginate($paginate);

        return $users;
    }

    public function update(User $user, array $userData, array $userInfoData = [], ?array $userConfigData = [])
    {
        $user->fill($userData);
        $user->save();

        if(!empty($userInfoData)){
            (new UserInfoService())->update($user, $userInfoData);
        }

        if(!empty($userConfigData)){
            (new UserConfigService())->update($user, $userConfigData);
        }
    }

    public function uploadAvatar(User $user, UploadedFile $image)
    {
        // Создание менеджера изображений
        $manager = new ImageManager(
            new Driver()
        );

        // Создание экземпляра изображения
        $img = $manager->read($image);

        // Изменение размера изображения, если необходимо
        if ($img->width() > $img->height()) {
            $img->scale(height: 240);
        } else {
            $img->scale(width: 180);
        }

        // Сохранение изображения
        $fileName = time() . '.' . $image->getClientOriginalExtension();
        $path = $user->getAvatarDir();
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path, 0755, true);
        }

        Storage::delete($user->getAvatarDir() . $user->avatar); // Удаляем прошлый автар
        $img->save(Storage::path($path . $fileName)); // Сохраняем новый аватар

        $user->avatar = $fileName;
        $user->save();
    }

    public function removeAvatar(User $user)
    {
        Storage::delete($user->getAvatarDir() . $user->avatar); // Удаляем прошлый автар
        Auth::user()->avatar = null;
        Auth::user()->save();
    }

    /**
     * Получает заданное количество случайных слов
     * @param int $id - идентификатор пользователя
     * @return mixed
     */
    public function getById(int $id){
        $user = User::with([
            'gender',
            'rang:id,title,alias',
            'infos',
            'config',
            'config.email',
            'config.facebook',
            'config.skype',
            'config.twitter',
            'config.vk',
            'config.odnoklassniki',
            'config.telegram',
            'config.whatsapp',
            'config.viber',
            'config.instagram',
            'config.youtube',
            'config.info',
            'config.residence',
            'config.sex',
            ])
            ->where('id', $id)
            ->get()
            ->first();

        return $user;
    }

    /**
     * Получаем количество зарегистрированных пользователей
     * @return int
     */
    public function countUsersRegister(){
        $usersCount = User::whereNotNull('email_verified_at')->count();

        return $usersCount;
    }

    /*
    static function userConfigsView(){
        $configView = UserConfigsView::select(['id', 'title', 'alias'])->get();
        return $configView->keyBy('id');
    }
    */

    /**
     * Добавляем объекты прав просмотра личной информации
     * @param User $user
     * @param UserConfigsView[] $configView
     * @return mixed
     */
    /*
    private function initAliasInfoView($user, $configView){
        // Поля коллекций, которые отвечают за доступ к данным пользователя
        $arrayFields = [
            'email', 'facebook',  'skype', 'twitter', 'vk', 'odnoklassniki', 'telegram', 'whatsapp', 'viber',
            'instagram', 'youtube', 'info',  'residence', 'sex'
        ];

        // Перечисляем поля настроек доступа к данным пользователя
        foreach ($user->config->getAttributes()  as $key => $value){

            // Проверяем соответствует ли текущее поле, полям заданным в массиве, значение должно быть числом
            if(
                in_array($key, $arrayFields) &&
                is_numeric($value)
            ){
                $user->config->setAttribute($key, $configView[$value]);
            }

        }
        return $user;
    }
*/

    /**
     * Убирает информацию о пользователе в зависимости от выставленных на нее прав
     * @param $user
     * @return mixed
     */
    /*
    private function filterInfo($user){

        if(\Auth::check() && $user->id == \Auth::id()){
            return $user;
        }

        if(\Auth::check()){ // для зарегистрированных пользователей
            if($user->config->email->alias != 'zaregistrirovannym' && $user->config->email->alias != 'vsem'){
                $user->email = null;
            }
            if($user->config->facebook->alias != 'zaregistrirovannym' && $user->config->facebook->alias != 'vsem'){
                $user->facebook = null;
            }
            if($user->config->info->alias != 'zaregistrirovannym' && $user->config->info->alias != 'vsem'){
                $user->info = null;
            }
            if($user->config->instagram->alias != 'zaregistrirovannym' && $user->config->instagram->alias != 'vsem'){
                $user->instagram = null;
            }
            if($user->config->odnoklassniki->alias != 'zaregistrirovannym' && $user->config->odnoklassniki->alias != 'vsem'){
                $user->odnoklassniki = null;
            }
            if($user->config->residence->alias != 'zaregistrirovannym' && $user->config->residence->alias != 'vsem'){
                $user->residence = null;
            }
            if($user->user->config->sex->alias != 'zaregistrirovannym' && $user->user->config->sex->alias != 'vsem'){
                $user->sex_title = null;
                $user->sex_id = null;
            }
            if($user->config->skype->alias != 'zaregistrirovannym' && $user->config->skype->alias != 'vsem'){
                $user->skype = null;
            }
            if($user->config->telegram->alias != 'zaregistrirovannym' && $user->config->telegram->alias != 'vsem'){
                $user->telegram = null;
            }
            if($user->config->twitter->alias != 'zaregistrirovannym' && $user->config->twitter->alias != 'vsem'){
                $user->twitter = null;
            }
            if($user->config->vk->alias != 'zaregistrirovannym' && $user->config->vk->alias != 'vsem'){
                $user->vk = null;
            }
            if($user->config->whatsapp->alias != 'zaregistrirovannym' && $user->config->whatsapp->alias != 'vsem'){
                $user->whatsapp = null;
            }
            if($user->config->youtube->alias != 'zaregistrirovannym' && $user->config->youtube->alias != 'vsem'){
                $user->youtube = null;
            }
            if($user->config->viber->alias != 'zaregistrirovannym' && $user->config->viber->alias != 'vsem'){
                $user->viber = null;
            }

        }elseif(false){ // для друзей

        }else{ // для всех

            if($user->config->email->alias != 'vsem'){
                $user->email = null;
            }
            if($user->config->facebook->alias != 'vsem'){
                $user->facebook = null;
            }
            if($user->config->info->alias != 'vsem'){
                $user->info = null;
            }
            if($user->config->instagram->alias != 'vsem'){
                $user->instagram = null;
            }
            if($user->config->odnoklassniki->alias != 'vsem'){
                $user->odnoklassniki = null;
            }
            if($user->config->residence->alias != 'vsem'){
                $user->residence = null;
            }
            if($user->config->sex->alias != 'vsem'){
                $user->sex_title = null;
                $user->sex_id = null;
            }
            if($user->config->skype->alias != 'vsem'){
                $user->skype = null;
            }
            if($user->config->telegram->alias != 'vsem'){
                $user->telegram = null;
            }
            if($user->config->twitter->alias != 'vsem'){
                $user->twitter = null;
            }
            if($user->config->vk->alias != 'vsem'){
                $user->vk = null;
            }
            if($user->config->whatsapp->alias != 'vsem'){
                $user->whatsapp = null;
            }
            if($user->config->youtube->alias != 'vsem'){
                $user->youtube = null;
            }
            if($user->config->viber->alias != 'vsem'){
                $user->viber = null;
            }
        }


        return $user;
    }
    */
    // Формирует url на соц сети
    private function formatSocialLinks($user){

        $user->infos->setAttribute('facebook_link', 'https://fb.com/' . $user->infos->facebook);
        $user->infos->setAttribute('odnoklassniki_link', 'https://fb.com/' . $user->infos->odnoklassniki);
        $user->infos->setAttribute('twitter_link', 'https://fb.com/' . $user->infos->twitter);
        $user->infos->setAttribute('vk_link', 'https://fb.com/' . $user->infos->vk);
        $user->infos->setAttribute('youtube_link', 'https://fb.com/' . $user->infos->youtube);
        $user->infos->setAttribute('instagram_link', 'https://fb.com/' . $user->infos->instagram);

        return $user;
    }
}
