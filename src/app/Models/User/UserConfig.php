<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserConfig extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'day_birthday',
        'yar_birthday',
        'email',
        'facebook',
        'skype',
        'twitter',
        'vk',
        'odnoklassniki',
        'telegram',
        'whatsapp',
        'viber',
        'instagram',
        'youtube',
        'info',
        'residence',
        'sex',
    ];

    protected $casts = [
        'day_birthday' => 'boolean',
        'yar_birthday' => 'boolean',
    ];

    public function email(){
        return $this->hasOne(UserConfigsView::class, 'id', 'email');
    }

    public function facebook(){
        return $this->hasOne(UserConfigsView::class, 'id', 'facebook');
    }

    public function skype(){
        return $this->hasOne(UserConfigsView::class, 'id', 'skype');
    }

    public function twitter(){
        return $this->hasOne(UserConfigsView::class, 'id', 'twitter');
    }

    public function vk(){
        return $this->hasOne(UserConfigsView::class, 'id', 'vk');
    }

    public function odnoklassniki(){
        return $this->hasOne(UserConfigsView::class, 'id', 'odnoklassniki');
    }

    public function telegram(){
        return $this->hasOne(UserConfigsView::class, 'id', 'telegram');
    }

    public function whatsapp(){
        return $this->hasOne(UserConfigsView::class, 'id', 'whatsapp');
    }

    public function viber(){
        return $this->hasOne(UserConfigsView::class, 'id', 'viber');
    }

    public function instagram(){
        return $this->hasOne(UserConfigsView::class, 'id', 'instagram');
    }

    public function youtube(){
        return $this->hasOne(UserConfigsView::class, 'id', 'youtube');
    }

    public function info(){
        return $this->hasOne(UserConfigsView::class, 'id', 'info');
    }

    public function residence(){
        return $this->hasOne(UserConfigsView::class, 'id', 'residence');
    }

    public function sex(){
        return $this->hasOne(UserConfigsView::class, 'id', 'sex');
    }
}
