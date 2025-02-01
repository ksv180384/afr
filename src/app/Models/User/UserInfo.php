<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'facebook',
        'skype',
        'twitter',
        'vk',
        'mail',
        'odnoklassniki',
        'telegram',
        'whatsapp',
        'viber',
        'instagram',
        'youtube',
    ];
}
