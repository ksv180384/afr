<?php

namespace App\Services\App;

use App\Models\Word\WordsPartOfSpeech;

class WordsPartOfSpeechService
{
    public function getById(int $id)
    {
        $partOfSpeech = WordsPartOfSpeech::query()->findOrFail($id);

        return $partOfSpeech;
    }

    public function getAll()
    {
        $partOfSpeech = WordsPartOfSpeech::all(['id', 'title']);
        return $partOfSpeech;
    }
}
