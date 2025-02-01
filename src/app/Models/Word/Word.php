<?php

namespace App\Models\Word;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    use HasFactory;
    use Filterable;

    protected $fillable = [
        'id_part_of_speech', // Ссылка на часть речи
        'word', // Слово
        'translation', // Перевод
        'transcription', // Транскрипция
        'example', // Пример
        'pronunciation', // Произношение
    ];

    public $timestamps = false;

    public function part_of_speech()
    {
        return $this->belongsTo(WordsPartOfSpeech::class, 'id_part_of_speech');
    }
}
