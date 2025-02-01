<?php

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Online extends Model
{
    use HasFactory;

    protected $table = 'online';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'token';

    protected $fillable = [
        'user_id',
        'token',
        'ip',
        'is_bot',
        'bot_name',
        'date',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'datetime:d.m.Y H:i:s',
    ];

    public function user(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    /**
     * Генерирует токен
     * @return string|void
     */
    public static function generateToken(){
        return bin2hex(random_bytes(16));
    }
}
