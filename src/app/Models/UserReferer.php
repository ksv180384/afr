<?php

namespace App\Models;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;

class UserReferer extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'referer_url',
        'landing_page',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'user_agent'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
