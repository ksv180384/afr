<?php
namespace App\Services\App;

use App\Services\App\Song\SongService;

class SearchService {

    public function search(string $text, string $type, string $lang = 'fr')
    {
        $songService = new SongService();
        $wordService = new WordService();

        $result = [];
        if($type === 'word'){
            $result = $wordService->search($text, $lang);
        }
        elseif ($type === 'song'){
            $result = $songService->search($text);
        }

        return $result;
    }
}
