<?php

namespace App\Models\User;

use App\Models\Player\PlayerSongs;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use Notifiable;

    const NO_AVATAR = '/img/none_avatar.png';

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'send_verified_email_at',
        'password',
        'avatar',
        'gender_id',
        'birthday',
        'info',
        'signature',
        'residence',
        'rang_id',
        'admin',
        'confirm_token',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime:d.m.Y H:i:s',
        'birthday' => 'date:Y-m-d',
    ];

    protected $appends = [
        'avatar_link',
    ];

    public function rang(){
        return $this->belongsTo(Rang::class, 'rang_id', 'id');
    }

    public function songs()
    {
        return $this->hasMany(PlayerSongs::class, 'user_id');
    }

    public function gender(){
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    public function infos(){
        return $this->hasOne(UserInfo::class);
    }

    public function config(){
        return $this->hasOne(UserConfig::class, 'user_id', 'id');
    }

    public function getIsAdminAttribute(){
        if(empty($this->rang)){
            throw ValidationException::withMessages(['message' => 'Невозможно определить права пользователя.']);
        }
        return $this->rang?->alias == 'administrator';
    }

    public function getIsModeratorAttribute(){
        if(empty($this->rang)){
            throw ValidationException::withMessages(['message' => 'Невозможно определить права пользователя.']);
        }
        return $this->rang->alias == 'moderator';
    }

    public function getisBanAttribute(){
        if(empty($this->rang)){
            throw ValidationException::withMessages(['message' => 'Невозможно определить права пользователя.']);
        }
        return $this->rang->alias == 'zabanen';
    }

    public function getAvatarLinkAttribute(): string
    {
        if(empty($this->avatar)){
            return asset(self::NO_AVATAR);
        }

        $fileName = basename($this->avatar);
        return Storage::url($this->getAvatarDir() . $fileName);
    }

//    public function setBirthdayAttribute($birthday)
//    {
//        $birthday = Carbon::parse($birthday)->format('Y-m-d H:i:s');
//        return $birthday;
//    }


    /**
     * Проверяем подтвердил ли пользователь емаил
     * @return bool
     */
    public function isConfirmed(){
        return !empty($this->email_verified_at);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getAvatarDir()
    {
        return 'user/' . $this->id . '/images/';
    }

    public function getMinData()
    {
        return ($this)->only([
            'id',
            'name',
            'avatar',
            'avatar_link',
            'rang_id',
            'is_admin',
            'is_moderator',
            'is_ban'
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {

            $configsView = UserConfigsView::where('alias', '=', UserConfigsView::NE_POKAZYVAT_NIKOMU)->first(['id']);

            UserConfig::create([
                'user_id' => $user->id,
                'day_birthday' => 0,
                'yar_birthday' => 0,
                'email' => $configsView->id,
                'facebook' => $configsView->id,
                'skype' => $configsView->id,
                'twitter' => $configsView->id,
                'vk' => $configsView->id,
                'odnoklassniki' => $configsView->id,
                'telegram' => $configsView->id,
                'whatsapp' => $configsView->id,
                'viber' => $configsView->id,
                'instagram' => $configsView->id,
                'youtube' => $configsView->id,
                'info' => $configsView->id,
                'residence' => $configsView->id,
                'sex' => $configsView->id,
            ]);

            UserInfo::create([
                'user_id' => $user->id,
            ]);
        });
    }
}
