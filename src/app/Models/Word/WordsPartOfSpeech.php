<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordsPartOfSpeech extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];

    protected $table = 'words_part_of_speeches';

    public $timestamps = false;
}
