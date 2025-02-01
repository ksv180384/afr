<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rang extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'alias',
    ];
    public $timestamps = false;
}
