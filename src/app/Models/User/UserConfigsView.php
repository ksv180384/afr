<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserConfigsView extends Model
{
    use HasFactory;

    const NE_POKAZYVAT_NIKOMU = 'ne-pokazyvat-nikomu';

    const ZAREGISTRIROVANNYM = 'zaregistrirovannym';

    const DRUZYAM = 'druzyam';

    const VSEM = 'vsem';

    protected $table = 'user_configs_views';

    protected $fillable = [
        'title',
        'alias',
    ];

    public $timestamps = false;
}
