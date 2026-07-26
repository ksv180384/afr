<?php

namespace App\Http\Resources\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'send_verified_email_at' => $this->send_verified_email_at,
            'avatar' => $this->avatar,
            'avatar_link' => $this->avatar_link,
            'gender_id' => $this->gender_id,
            'gender' => $this->gender,
            'birthday' => $this->birthday,
            'info' => $this->info,
            'signature' => $this->signature,
            'residence' => $this->residence,
            'rang_id' => $this->rang_id,
            'rang' => $this->rang,
            'admin' => (bool) $this->admin,
            'is_admin' => $this->is_admin,
            'is_moderator' => $this->is_moderator,
            'is_ban' => $this->is_ban,
            'confirm_token' => $this->confirm_token,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'infos' => [
                'facebook' => data_get($this, 'infos.facebook'),
                'skype' => data_get($this, 'infos.skype'),
                'twitter' => data_get($this, 'infos.twitter'),
                'vk' => data_get($this, 'infos.vk'),
                'odnoklassniki' => data_get($this, 'infos.odnoklassniki'),
                'telegram' => data_get($this, 'infos.telegram'),
                'whatsapp' => data_get($this, 'infos.whatsapp'),
                'viber' => data_get($this, 'infos.viber'),
                'instagram' => data_get($this, 'infos.instagram'),
                'youtube' => data_get($this, 'infos.youtube'),
            ],
            'config' => [
                'day_birthday' => (bool) data_get($this, 'config.day_birthday'),
                'yar_birthday' => (bool) data_get($this, 'config.yar_birthday'),
                'email' => data_get($this, 'config.email'),
                'facebook' => data_get($this, 'config.facebook'),
                'skype' => data_get($this, 'config.skype'),
                'twitter' => data_get($this, 'config.twitter'),
                'vk' => data_get($this, 'config.vk'),
                'odnoklassniki' => data_get($this, 'config.odnoklassniki'),
                'telegram' => data_get($this, 'config.telegram'),
                'whatsapp' => data_get($this, 'config.whatsapp'),
                'viber' => data_get($this, 'config.viber'),
                'instagram' => data_get($this, 'config.instagram'),
                'youtube' => data_get($this, 'config.youtube'),
                'info' => data_get($this, 'config.info'),
                'residence' => data_get($this, 'config.residence'),
                'sex' => data_get($this, 'config.sex'),
            ],
        ];
    }
}
