<?php

namespace App\Http\Resources\App\User;

use App\Helpers\Helper;
use Carbon\Carbon;
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
//        dd(data_get($this, 'config.email.alias'));

        $result = [
            'id' => $this['id'],
            'avatar_link' => $this['avatar_link'],
            'name' => $this['name'],
            'rang' => $this['rang'],
            'created_at' => Carbon::parse($this['created_at'])->format('d.m.Y'),
        ];

        if(data_get($this, 'config.day_birthday')){
            $result['day_birthday'] = $this['birthday'] ? Carbon::parse($this['birthday'])->format('d.m') : '';
        }
        if(data_get($this, 'config.yar_birthday')){
            $result['yar_birthday'] = $this['birthday'] ? Carbon::parse($this['birthday'])->format('Y') : '';
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.sex.alias'))){
            $result['gender'] = $this['gender'];
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.email.alias'))){
            $result['email'] = $this['email'];
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.info.alias'))){
            $result['info'] = $this['info'];
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.residence.alias'))){
            $result['residence'] = $this['residence'];
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.facebook.alias'))){
            $result['facebook'] = data_get($this, 'infos.facebook');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.instagram.alias'))){
            $result['instagram'] = data_get($this, 'infos.instagram');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.odnoklassniki.alias'))){
            $result['ok'] = data_get($this, 'infos.odnoklassniki');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.skype.alias'))){
            $result['skype'] = data_get($this, 'infos.skype');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.telegram.alias'))){
            $result['telegram'] = data_get($this, 'infos.telegram');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.twitter.alias'))){
            $result['twitter'] = data_get($this, 'infos.twitter');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.viber.alias'))){
            $result['viber'] = data_get($this, 'infos.viber');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.vk.alias'))){
            $result['vk'] = data_get($this, 'infos.vk');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.whatsapp.alias'))){
            $result['whatsapp'] = data_get($this, 'infos.whatsapp');
        }
        if(Helper::isShowInfoUser(data_get($this, 'config.youtube.alias'))){
            $result['youtube'] = data_get($this, 'infos.youtube');
        }

        return $result;
    }
}
