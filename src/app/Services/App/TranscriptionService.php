<?php

namespace App\Services\App;

use App\Models\Word\Word;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TranscriptionService
{
    private $transcriptionServerPath;

    public function __construct()
    {
        $this->transcriptionServerPath = env('TRANSLATOR_SERVER_PATH');
    }

    public function train()
    {
        $words = Word::all(['id', 'word', 'transcription', 'example']);

        $newWords = [];
        foreach ($words as $word){
            $newWords[] = [
                'phrase' => $word->word,
                'transcription' => $word->transcription
            ];

            preg_match_all('/<p>(.*?) =&gt; (.*?) -/', $word->example, $matches);
            foreach ($matches[1] as $key => $phrase) {
                $newWords[] = [
                    'phrase' => $phrase,
                    'transcription' => trim($matches[2][$key])
                ];
            }
        }

//        $words = $words->map(function ($item) {
//            preg_match_all('/<p>(.*?) =&gt; (.*?) -/', $item->example, $matches);
//            foreach ($matches[1] as $key => $phrase) {
//                $result[] = [$phrase => trim($matches[2][$key])];
//            }
//            return [
//                'word' => $item->word,
//                'transcription' => $item->transcription,
////                'word' => $item->word,
//            ];
//        });


        $newWords = array_slice($newWords, 0, 10);

//        dd($newWords);

//        dd([
//            'words' => $test,
//        ]);

        $response = Http::post($this->transcriptionServerPath . '/training/train', [
            'words' => $newWords,
        ]);

        if (!$response->successful()) {
            throw ValidationException::withMessages(['message' => 'Ошибка при попытке тренировки модели.']);
        }
        $res = $response->json();

        return !empty($res) ? $res : null;
    }

    public function transcribe(string $text)
    {
        $response = Http::post($this->transcriptionServerPath . '/transcription/transcribe', [
            'text' => $text,
        ]);

        if (!$response->successful()) {
            throw ValidationException::withMessages(['message' => 'Ошибка при получении транскрипции.']);
        }
        $res = $response->json();

        return !empty($res) ? $res : null;
    }
}
