<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proverb extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'translation',
    ];

    public $timestamps = false;
}
