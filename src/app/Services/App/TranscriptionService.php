<?php

namespace App\Services\App;

use App\Models\Word\Word;
use Illuminate\Support\Arr;
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


//        $newWords = array_slice($newWords, 0, 10);
//        dd(count($newWords));

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
/*
    public function transcribe(string $text)
    {
//        $response = Http::post($this->transcriptionServerPath . '/transcription/transcribe', [
//            'text' => $text,
//        ]);
//
//        if (!$response->successful()) {
//            throw ValidationException::withMessages(['message' => 'Ошибка при получении транскрипции.']);
//        }
//        $res = $response->json();
//
//        return !empty($res) ? $res : null;

        $result = $this->replaceNnémentWithNyeMye($text);
        $result = $this->replaceFrenchEClosedSyllableUnderStress($text);
        $result = $this->removeConsonantsSilentEndings($text);
        $result = $this->replaceUnstressedE($text);
        $result = $this->replaceTtreEndingWithTr($text);
        $result = $this->replaceErEndingWithE($text);
        $result = $this->replaceFleetingE($text);
        $result = $this->replaceReEndingWithR($text);
        $result = $this->removeFinalE($text);
        $result = $this->replaceErEnding($text);
        $result = $this->replaceAWithCyrillicA($text);
        $result = $this->replaceIWithCyrillicI($text);
        $result = $this->replaceOWithCyrillicO($text);
        $result = $this->replaceUWithCyrillicYu($text);
        $result = $this->replaceOuWithCyrillicU($text);
        $result = $this->replaceLWithCyrillic($text);
        $result = $this->replaceCWithCyrillic($text);
        $result = $this->removeSilentH($text);
        $result = $this->replaceChWithCyrillicSh($text);
        $result = $this->replacePhWithCyrillicF($text);
        $result = $this->replaceTAndTh($text);
        $result = $this->replacePWithCyrillicP($text);
        $result = $this->replaceJWithCyrillicZh($text);
        $result = $this->replaceYWithCyrillic($text);
        $result = $this->replaceGWithCyrillic($text);
        $result = $this->replaceGnWithCyrillic($text);
        $result = $this->replaceCedillaWithCyrillic($text);
        $result = $this->replaceSWithCyrillic($text);
        $result = $this->replaceNWithCyrillicN($text);
        $result = $this->replaceZWithCyrillicZ($text);
        $result = $this->replaceAiEiWithCyrillicE($text);
        $result = $this->replaceEuWithCyrillicYo($text);
        $result = $this->replaceAuEauWithCyrillicO($text);
        $result = $this->replaceOiWithYa($text);
        $result = $this->replaceQuWithK($text);
        $result = $this->replaceGuWithG($text);
        $result = $this->replaceIlWithYOrIy($text);
        $result = $this->replaceAnAmEnEmWithAn($text);
        $result = $this->replaceOnOmWithOn($text);
        $result = $this->replaceInUnAinEinYnWithEn($text);
        $result = $this->replaceImUmAimYmWithEm($text);
        $result = $this->replaceTiWithCOrT($text);
        $result = $this->replaceXWithRules($text);
        $result = $this->replaceNnWithN($text);

        return $result;
    }
*/
    function transcribe($text) {
        // Разбиваем текст на слова
        $words = explode(' ', $text);
        $transcribedWords = [];

        $functionsList = [
            'replaceNnementWithNyeMye', // 7 - конец слова
            'replaceTtreEndingWithTr', // 3 - конец слова
            'replaceLleEndingWithL', // 3 - конец слова
            'replaceNneEndingWithN', // 3 - конец слова
            'replaceErEnding', // 2 - конец слова
            'replaceErEndingWithE', // 2 - конец слова
            'replaceReEndingWithR', // 2 - конец слова
            'removeFinalE', // 1 или 2 - конец слова
            'removeConsonantsSilentEndings', // 1 - конец слова
            'replaceCieWithSy', // 3
            'replaceEuWithCyrillicYo', // 2 -3
            'replaceAuEauWithCyrillicO', // 2 -3
            'replaceIlWithYOrIy', // 2 -3
            'replaceInUnAinEinYnWithEn', // 2 -3
            'replaceImUmAimYmWithEm', // 2 -3
            'replaceNnWithN', // 2
            'replaceOuWithCyrillicU', // 2
            'replaceChWithCyrillicSh', // 2
            'replacePhWithCyrillicF', // 2
            'replaceGnWithCyrillic', // 2
            'replaceAiEiWithCyrillicE', // 2
            'replaceOiWithYa', // 2
            'replaceQuWithK', // 2
            'replaceGuWithG', // 2
            'replaceAnWithAn', // 2
//            'replaceAnEnWithAn', // 2
            'replaceEnWithYnOrAn', // 2
            'replaceEmWithYm', // 2
            'replaceOnOmWithOn', // 2
            'replaceTiWithCOrT', // 2
            'replaceAllCcWithK', // 2
            'replaceTAndTh', // 1 - 2
            'replaceCEndingWithEmpty', // 1 - конец слова
            'replaceFleetingE', // 1 - середина слова
            'replaceFrenchEClosedSyllableUnderStress', // 1 - середина слова
            'replaceUnstressedE', // 1 - в безударном
            'replaceAWithCyrillicA', // 1
            'replaceIWithCyrillicI', // 1
            'replaceOWithCyrillicO', // 1
            'replaceUWithCyrillicYu', // 1
            'replaceYWithCyrillic', // 1
            'replaceLWithCyrillic', // 1
            'replaceGWithCyrillic', // 1
            'replaceCWithCyrillic', // 1
            'replaceCedillaWithCyrillic', // 1
            'removeSilentH', // 1
            'replacePWithCyrillicP', // 1
            'replaceJWithCyrillicZh', // 1
            'replaceSWithCyrillic', // 1
            'replaceNWithCyrillicN', // 1
            'replaceZWithCyrillicZ', // 1
            'replaceXWithRules', // 1
            'replaceFWithF', // 1
            'replaceBWithB', // 1
            'replaceDWithD', // 1
            'replaceMWithM', // 1
            'replaceRWithR', // 1
        ];

        foreach ($words as $word) {
            foreach ($functionsList as $function) {
                $trItems = $this->$function($word, $function);
                if ($trItems) {
                    foreach ($trItems as $trItem) {
                        if (!$this->isPositionInArray($trItem, $transcribedWords)) {
                            $transcribedWords[] = $trItem;
                        }
                    }
                }
            }
        }


        // enfant
//        dd($transcribedWords);
//        $transcribedWords = array_values($transcribedWords);

        // Сортируем результаты по позиции
        usort($transcribedWords, function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });
        // Применяем правило переноса звуков между словами
        $transcribedText = implode(' ', $words); // Объединяем слова в одну строку
        $liaisonResults = $this->handleLiaison($transcribedText);

        // Добавляем результаты переноса звуков в общий массив
        foreach ($liaisonResults as $liaisonItem) {
            if (!$this->isPositionInArray($liaisonItem, $transcribedWords)) {
                $transcribedWords[] = $liaisonItem;
            }
        }

    // Снова сортируем результаты по позиции
        usort($transcribedWords, function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        // Применяем все замены к тексту
        foreach ($transcribedWords as $trItem) {
            $transcribedText = substr_replace($transcribedText, $trItem['replacement'], $trItem['position'], $trItem['length']);
        }


        dump(collect($transcribedWords)->pluck('replacement'));
        dump(collect($transcribedWords)->pluck('replace'));
        dd(collect($transcribedWords)->map(function ($item){
            return ['replacement' => $item['replacement'], 'replace' => $item['replace'], 'position' => $item['position'], 'function' => $item['function']];
        }));
        dd($transcribedWords);

        return $transcribedWords;
/*
        // Обрабатываем каждое слово
//        foreach ($words as $word) {

//            $word = $this->applyReplacements($word, 'replaceLleEndingWithL'); // 3 - конец слова
//            $word = $this->applyReplacements($word, 'replaceErEnding'); // 2 - конец слова
//            $word = $this->applyReplacements($word, 'removeFinalE'); // 1 или 2 - конец слова
//            $word = $this->applyReplacements($word, 'removeConsonantsSilentEndings'); // 1 - конец слова
//            $word = $this->applyReplacements($word, 'replaceUnstressedE'); // 1 - конец слова
//            $word = $this->applyReplacements($word, 'replaceEuWithCyrillicYo'); // 2 -3
//            $word = $this->applyReplacements($word, 'replaceAuEauWithCyrillicO'); // 2 -3
//            $word = $this->applyReplacements($word, 'replaceIlWithYOrIy'); // 2 -3
//            $word = $this->applyReplacements($word, 'replaceInUnAinEinYnWithEn'); // 2 -3
//            $word = $this->applyReplacements($word, 'replaceImUmAimYmWithEm'); // 2 -3
//            $word = $this->applyReplacements($word, 'replaceOuWithCyrillicU'); // 2
//            $word = $this->applyReplacements($word, 'replaceChWithCyrillicSh'); // 2
//            $word = $this->applyReplacements($word, 'replacePhWithCyrillicF'); // 2
//            $word = $this->applyReplacements($word, 'replaceGnWithCyrillic'); // 2
//            $word = $this->applyReplacements($word, 'replaceAllCcWithK'); // 2
//            $word = $this->applyReplacements($word, 'replaceAiEiWithCyrillicE'); // 2
//            $word = $this->applyReplacements($word, 'replaceOiWithYa'); // 2
//            $word = $this->applyReplacements($word, 'replaceQuWithK'); // 2
//            $word = $this->applyReplacements($word, 'replaceGuWithG'); // 2
//            $word = $this->applyReplacements($word, 'replaceAnWithAn'); // 2
//            $word = $this->applyReplacements($word, 'replaceEnWithYnOrAn'); // 2
//            $word = $this->applyReplacements($word, 'replaceAmWithAm'); // 2
//            $word = $this->applyReplacements($word, 'replaceEmWithYm'); // 2
//            $word = $this->applyReplacements($word, 'replaceOnOmWithOn'); // 2
//            $word = $this->applyReplacements($word, 'replaceTiWithCOrT'); // 2
//            $word = $this->applyReplacements($word, 'replaceTAndTh'); // 1 - 2
//            $word = $this->applyReplacements($word, 'replaceFleetingE'); // 1 - середина слова
//            $word = $this->applyReplacements($word, 'replaceFrenchEClosedSyllableUnderStress'); // 1 - середина слова
//            $word = $this->applyReplacements($word, 'replaceAWithCyrillicA'); // 1
//            $word = $this->applyReplacements($word, 'replaceIWithCyrillicI'); // 1
//            $word = $this->applyReplacements($word, 'replaceOWithCyrillicO'); // 1
//            $word = $this->applyReplacements($word, 'replaceUWithCyrillicYu'); // 1
//            $word = $this->applyReplacements($word, 'replaceYWithCyrillic'); // 1
//            $word = $this->applyReplacements($word, 'replaceLWithCyrillic'); // 1
//            $word = $this->applyReplacements($word, 'replaceGWithCyrillic'); // 1
//            $word = $this->applyReplacements($word, 'replaceCWithCyrillic'); // 1
//            $word = $this->applyReplacements($word, 'replaceCedillaWithCyrillic'); // 1
//            $word = $this->applyReplacements($word, 'removeSilentH'); // 1
//            $word = $this->applyReplacements($word, 'replacePWithCyrillicP'); // 1
//            $word = $this->applyReplacements($word, 'replaceJWithCyrillicZh'); // 1
//            $word = $this->applyReplacements($word, 'replaceSWithCyrillic'); // 1
//            $word = $this->applyReplacements($word, 'replaceNWithCyrillicN'); // 1
//            $word = $this->applyReplacements($word, 'replaceZWithCyrillicZ'); // 1
//            $word = $this->applyReplacements($word, 'replaceXWithRules'); // 1
//            $word = $this->applyReplacements($word, 'replaceFWithF'); // 1
//            $word = $this->applyReplacements($word, 'replaceBWithB'); // 1
//            $word = $this->applyReplacements($word, 'replaceDWithD'); // 1


            // Добавляем обработанное слово в массив
//            $transcribedWords[] = $word;
//        }

        // Собираем текст обратно
        return implode(' ', $transcribedWords);
*/
    }

    private function handleLiaison($text) {
        $results = [];
        $words = explode(' ', $text);
        $length = count($words);

        for ($i = 0; $i < $length - 1; $i++) {
            $currentWord = $words[$i];
            $nextWord = $words[$i + 1];

            // Пример: если текущее слово заканчивается на согласный, а следующее начинается с гласного
            if (preg_match('/[bcdfghjklmnpqrstvwxyz]$/i', $currentWord) && preg_match('/^[aeiouy]/i', $nextWord)) {
                $results[] = [
                    'function' => 'handleLiaison',
                    'description' => 'Liaison between words',
                    'replacement' => '-', // Например, добавляем дефис для обозначения переноса
                    'replace' => ' ',
                    'position' => strlen(implode(' ', array_slice($words, 0, $i + 1))),
                    'length' => 1,
                ];
            }
        }

        return $results;
    }

    private function isPositionInArray($position, $array) {
        foreach ($array as $element) {
            if (isset($element['position']) && isset($element['length'])) {
                $start = $element['position'];
                $end = $start + $element['length'] - 1;
                if ($position['position'] >= $start && $position['position'] <= $end) {
                    return true; // Позиция найдена
                }
            }
        }
        return false; // Позиция не найдена
    }

    private static function replaceFrenchEClosedSyllableUnderStress($word)
    {
        // Определяем символы, которые нужно обрабатывать
        $targetChars = ['е', 'è', 'ê', 'é', 'ё', 'ë', 'e']; // Добавили обычную "e"
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y', 'é', 'è', 'ê', 'ë', 'ё', 'е']; // Гласные

        // Находим позицию ударного слога
        $stressedPos = -1;

        // Сначала ищем слоги с é, è, ê, ë
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);
            if (in_array($currentChar, ['é', 'è', 'ê', 'ë'])) {
                $stressedPos = $i;
                break;
            }
        }

        // Если ударная гласная не найдена, считаем ударным последний слог
        if ($stressedPos === -1) {
            for ($i = mb_strlen($word) - 1; $i >= 0; $i--) {
                $currentChar = mb_substr($word, $i, 1);
                if (in_array($currentChar, $vowels)) {
                    $stressedPos = $i;
                    break;
                }
            }
        }

        // Если ударный слог найден
        if ($stressedPos !== -1) {
            // Проходим по слову
            for ($i = 0; $i < mb_strlen($word); $i++) {
                $currentChar = mb_substr($word, $i, 1);

                // Если текущий символ - один из целевых символов
                if (in_array($currentChar, $targetChars)) {
                    // Проверяем, находится ли символ в ударном слоге
                    if ($i <= $stressedPos) {
                        // Проверяем, является ли слог закрытым (следующий символ - согласная)
                        $nextChar = ($i + 1 < mb_strlen($word)) ? mb_substr($word, $i + 1, 1) : '';
                        if (!in_array($nextChar, $vowels)) {
                            // Возвращаем результат
                            return [[
                                'function' => 'replaceFrenchEClosedSyllableUnderStress',
                                'description' => 'е, è, ê, é, ё под ударением и в закрытом слоге читается как "э"',
                                'replacement' => 'э',
                                'replace' => $currentChar,
                                'position' => $i,
                                'length' => mb_strlen($currentChar) // Количество символов для замены
                            ]];
                        }
                    }
                }
            }
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function removeConsonantsSilentEndings($word)
    {
        // Определяем символы, которые не читаются на конце слов
        $silentEndings = ['s', 't', 'd', 'z', 'x', 'p', 'g'];

        // Проходим по слову с конца
        $length = mb_strlen($word);
        $position = $length - 1;
        $charsToRemove = '';

        while ($position >= 0) {
            $currentChar = mb_substr($word, $position, 1);

            // Если текущий символ - один из "молчащих" символов
            if (in_array($currentChar, $silentEndings)) {
                $charsToRemove = ''; // Добавляем символ в начало строки
            } else {
                break; // Прерываем цикл, если символ не подходит
            }
            $position--;
        }
        // Если найдены символы для удаления
        if ($charsToRemove !== '') {
            return [[
                'function' => 'removeSilentEndings',
                'description' => '-s, -t, -d, -z, -x, -p, -g (а также их сочетания) на конце слов НЕ ЧИТАЮТСЯ.',
                'replacement' => '', // Меняем на пустую строку
                'replace' => $charsToRemove,
                'position' => $position, // Позиция первого удаляемого символа
                'length' => mb_strlen($charsToRemove) // Количество символов для удаления
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceUnstressedE($word)
    {
        // Определяем символы, которые нужно обрабатывать
        $targetChar = 'e';
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y', 'é', 'è', 'ê', 'ë', 'ё', 'е']; // Гласные

        // Находим позицию последней гласной в слове (ударный слог)
        $lastVowelPos = -1;
        for ($i = mb_strlen($word) - 1; $i >= 0; $i--) {
            $currentChar = mb_substr($word, $i, 1);
            if (in_array($currentChar, $vowels)) {
                $lastVowelPos = $i;
                break;
            }
        }

        // Если ударный слог найден
        if ($lastVowelPos !== -1) {
            // Проходим по слову
            for ($i = 0; $i < mb_strlen($word); $i++) {
                $currentChar = mb_substr($word, $i, 1);

                // Если текущий символ - "e" и он в безударном слоге
                if ($currentChar === $targetChar && $i < $lastVowelPos) {
                    // Возвращаем результат
                    return [[
                        'function' => 'replaceUnstressedE',
                        'description' => 'e в безударном слоге читается примерно как немецкое "ö" – как буква "ё"',
                        'replacement' => 'ё', // Меняем на "ё"
                        'replace' => 'e',
                        'position' => $i, // Позиция символа "e"
                        'length' => 1 // Количество символов для замены
                    ]];
                }
            }
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceFleetingE($word)
    {
        // Определяем символы, которые нужно обрабатывать
        $targetChar = 'e';
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y', 'é', 'è', 'ê', 'ë']; // Гласные
        $consonants = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'z']; // Согласные

        // Находим позиции первого и последнего слогов
        $firstSyllableEnd = -1;
        $lastSyllableStart = -1;

        // Ищем конец первого слога (первая гласная)
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);
            if (in_array($currentChar, $vowels)) {
                $firstSyllableEnd = $i;
                break;
            }
        }

        // Ищем начало последнего слога (последняя гласная)
        for ($i = mb_strlen($word) - 1; $i >= 0; $i--) {
            $currentChar = mb_substr($word, $i, 1);
            if (in_array($currentChar, $vowels)) {
                $lastSyllableStart = $i;
                break;
            }
        }

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "e"
            if ($currentChar === $targetChar) {
                // Проверяем, находится ли "e" в середине слова (между первым и последним слогом)
                if ($i > $firstSyllableEnd && $i < $lastSyllableStart) {
                    // Проверяем, является ли слог открытым (после "e" идёт гласная)
                    $nextChar = mb_substr($word, $i + 1, 1);
                    $nextNextChar = mb_substr($word, $i + 2, 1);
                    if (!in_array($nextChar, $vowels) && in_array($nextNextChar, $vowels)) {
                        // Возвращаем результат
                        return [[
                            'function' => 'replaceFleetingE',
                            'description' => 'В середине слов в открытом слоге эта буква при произношении выбрасывается вовсе (e беглое). Так, например, слово carrefou (carrefour,  Épicerie, Madeleine)',
                            'replacement' => "'", // Меняем на апостроф
                            'replace' => "e",
                            'position' => $i, // Позиция символа "e"
                            'length' => 1 // Количество символов для замены
                        ]];
                    }
                }
            }
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function removeFinalE($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'e';

        // Проверяем, заканчивается ли слово на "e" или "es"
        $lastChar = mb_substr($word, -1, 1);
        $lastTwoChars = mb_substr($word, -2, 2);

        if ($lastChar === $targetChar || $lastTwoChars === 'es') {
            // Проверяем, является ли слово односложным
            if (!self::isMonosyllabic($word)) {
                // Определяем позицию и длину для замены
                if ($lastTwoChars === 'es') {
                    // Удаляем "es"
                    return [[
                        'function' => 'removeFinalE',
                        'description' => 'в конце слов не читается, в односложных словах e в конце слов читается, нечитаемое окончание -s , образующее  множественное число у существительных',
                        'replacement' => '', // Меняем на пустую строку
                        'replace' => 'es',
                        'position' => mb_strlen($word) - 2, // Позиция символа "e"
                        'length' => 2 // Количество символов для замены
                    ]];
                } else {
                    // Удаляем "e"
                    return [[
                        'function' => 'removeFinalE',
                        'description' => 'в конце слов не читается, в односложных словах e в конце слов читается, нечитаемое окончание -s , образующее  множественное число у существительных',
                        'replacement' => '', // Меняем на пустую строку
                        'replace' => 'e',
                        'position' => mb_strlen($word) - 1, // Позиция символа "e"
                        'length' => 1 // Количество символов для замены
                    ]];
                }
            }
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    // Функция для проверки, является ли слово односложным
    private static function isMonosyllabic($word)
    {
        // Примерный список односложных слов (артикли, предлоги, местоимения и т.д.)
        $monosyllabicWords = ['le', 'la', 'les', 'me', 'te', 'se', 'je', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles', 'ce', 'cet', 'cette', 'ces', 'de', 'du', 'des', 'à', 'au', 'aux', 'en', 'ne', 'que', 'qui', 'quoi', 'si', 'mais', 'et', 'ou', 'où', 'donc', 'or', 'ni', 'car'];

        // Проверяем, есть ли слово в списке односложных
        return in_array($word, $monosyllabicWords);
    }

    private static function replaceErEnding($word)
    {
        // Определяем окончание, которое нужно обрабатывать
        $targetEnding = 'er';

        // Проверяем, заканчивается ли слово на "er"
        $lastTwoChars = mb_substr($word, -2, 2);
        if ($lastTwoChars === $targetEnding) {
            // Возвращаем результат
            return [[
                'function' => 'replaceErEnding',
                'description' => '-er на концах слов читается как "е"',
                'replacement' => 'е', // Меняем на "е"
                'replace' => 'er', // Меняем на "е"
                'position' => mb_strlen($word) - 2, // Позиция окончания "er"
                'length' => 2 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceAWithCyrillicA($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'a';
        $replacementChar = 'а'; // Кириллическая "а"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "a"
            if ($currentChar === $targetChar) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceAWithCyrillicA',
                    'description' => 'a — читается как "а"',
                    'replacement' => $replacementChar, // Меняем на "а"
                    'replace' => 'а',
                    'position' => $i, // Позиция символа "a"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceIWithCyrillicI($word)
    {
        // Определяем символы, которые нужно обрабатывать
        $targetChars = ['i', 'î', 'ï', 'ì', 'í'];
        $replacementChar = 'и'; // Кириллическая "и"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - один из целевых символов
            if (in_array($currentChar, $targetChars)) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceIWithCyrillicI',
                    'description' => 'i (в том числе со значками) — читается как "и": vie',
                    'replacement' => $replacementChar, // Меняем на "и"
                    'replace' => 'i', // Меняем на "и"
                    'position' => $i, // Позиция символа
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceOWithCyrillicO($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'o';
        $replacementChar = 'о'; // Кириллическая "о"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "o"
            if ($currentChar === $targetChar) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceOWithCyrillicO',
                    'description' => 'o – читается как "о": locomotive  [локомотив]',
                    'replacement' => $replacementChar, // Меняем на "о"
                    'replace' => $targetChar,
                    'position' => $i, // Позиция символа "o"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceUWithCyrillicYu($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'u';
        $replacementChar = 'ю'; // Кириллическая "ю"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "u"
            if ($currentChar === $targetChar) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceUWithCyrillicYu',
                    'description' => 'u читается как "ю" в слове "мюсли". Пример: cuvette  читается [кювет] и означает "кювет", parachute  [парашют] — означает "парашют" :), то же происходит и c purée  (пюре), и c confiture  (варенье).',
                    'replacement' => $replacementChar, // Меняем на "ю"
                    'replace' => $targetChar, // Меняем на "ю"
                    'position' => $i, // Позиция символа "u"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceOuWithCyrillicU($word)
    {
        // Определяем сочетание, которое нужно обрабатывать
        $targetCombination = 'ou';
        $replacementChar = 'у'; // Кириллическая "у"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            $currentCombination = mb_substr($word, $i, 2);

            // Если текущее сочетание - "ou"
            if ($currentCombination === $targetCombination) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceOuWithCyrillicU',
                    'description' => 'открытый звук "у", используется сочетание ou (это привычно из английского: you, group [груп], router [рутер], tour [тур]).',
                    'replacement' => $replacementChar, // Меняем на "у"
                    'replace' => $targetCombination,
                    'position' => $i, // Позиция сочетания "ou"
                    'length' => 2 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceYWithCyrillic($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'y';
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y', 'é', 'è', 'ê', 'ë']; // Гласные

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "y"
            if ($currentChar === $targetChar) {
                // Определяем, находится ли "y" рядом с гласными
                $prevChar = ($i > 0) ? mb_substr($word, $i - 1, 1) : '';
                $nextChar = ($i < mb_strlen($word) - 1) ? mb_substr($word, $i + 1, 1) : '';

                // Если "y" рядом с гласными, заменяем на "й"
                if (in_array($prevChar, $vowels) || in_array($nextChar, $vowels)) {
                    $replacements[] = [
                        'function' => 'replaceYWithCyrillic',
                        'description' => 'y читается как [i]. Но рядом с гласными й',
                        'replacement' => 'й', // Меняем на "й"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "y"
                        'length' => 1 // Количество символов для замены
                    ];
                } else {
                    // Иначе заменяем на "и"
                    $replacements[] = [
                        'function' => 'replaceYWithCyrillic',
                        'description' => 'y читается как [i]. Но рядом с гласными й',
                        'replacement' => 'и', // Меняем на "и"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "y"
                        'length' => 1 // Количество символов для замены
                    ];
                }
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceLWithCyrillic($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'l';

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "l"
            if ($currentChar === $targetChar) {
                // Проверяем, находится ли "l" в конце слова
                if ($i === mb_strlen($word) - 1) {
                    // Если "l" в конце слова, заменяем на "ль"
                    $replacements[] = [
                        'function' => 'replaceLWithCyrillic',
                        'description' => 'l читается как "л", на конце слова смягченно "ль"',
                        'replacement' => 'ль', // Меняем на "ль"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "l"
                        'length' => 1 // Количество символов для замены
                    ];
                } else {
                    // Если "l" не в конце слова, заменяем на "л"
                    $replacements[] = [
                        'function' => 'replaceLWithCyrillic',
                        'description' => 'l читается как "л", на конце слова смягченно "ль"',
                        'replacement' => 'л', // Меняем на "л"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "l"
                        'length' => 1 // Количество символов для замены
                    ];
                }
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceGWithCyrillic($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'g';
        $softeningChars = ['e', 'i', 'y', 'é', 'è', 'ê', 'ë']; // Гласные, перед которыми "g" читается как "ж"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "g"
            if ($currentChar === $targetChar) {
                // Проверяем следующий символ
                $nextChar = ($i < mb_strlen($word) - 1) ? mb_substr($word, $i + 1, 1) : '';

                // Если следующий символ - e, i, y или их варианты, заменяем на "ж"
                if (in_array($nextChar, $softeningChars)) {
                    $replacements[] = [
                        'function' => 'replaceGWithCyrillic',
                        'description' => 'g читается как "г", но перед е, i и y она читается, как "ж".',
                        'replacement' => 'ж', // Меняем на "ж"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "g"
                        'length' => 1 // Количество символов для замены
                    ];
                } else {
                    // Иначе заменяем на "г"
                    $replacements[] = [
                        'function' => 'replaceGWithCyrillic',
                        'description' => 'g читается как "г", но перед е, i и y она читается, как "ж".',
                        'replacement' => 'г', // Меняем на "г"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "g"
                        'length' => 1 // Количество символов для замены
                    ];
                }
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceGnWithCyrillic($word)
    {
        // Определяем сочетание, которое нужно обрабатывать
        $targetCombination = 'gn';
        $replacementCombination = 'нь'; // Кириллическое "нь"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            $currentCombination = mb_substr($word, $i, 2);

            // Если текущее сочетание - "gn"
            if ($currentCombination === $targetCombination) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceGnWithCyrillic',
                    'description' => 'Буквосочетание gn читается как [нь] — например, в названии города Cognac  [коньак] — Коньяк,',
                    'replacement' => $replacementCombination, // Меняем на "нь"
                    'replace' => $targetCombination,
                    'position' => $i, // Позиция сочетания "gn"
                    'length' => 2 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceCWithCyrillic($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'c';
        $softeningChars = ['e', 'i', 'y', 'é', 'è', 'ê', 'ë']; // Гласные, перед которыми "c" читается как "с"

        // Слова, в которых "c" на конце не читается
        $silentEndingWords = ['blanc', 'estomac', 'tabac'];

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "c"
            if ($currentChar === $targetChar) {
                // Проверяем, находится ли "c" в конце слова
                if ($i === mb_strlen($word) - 1) {
                    // Если "c" в конце слова, проверяем, нужно ли её удалить
                    if (in_array($word, $silentEndingWords)) {
                        // Если слово в списке исключений, удаляем "c"
                        $replacements[] = [
                            'function' => 'replaceCWithCyrillic',
                            'description' => 'c читается как "к", mascarade  [маскарад], уже упоминаемые нами compote  и cuvette . Но перед тремя гласными е, i и y она читается, как "с". Например: certificat  читается [сертифика], vélocipède  — [велосипед], motocycle  — [мотосикль].',
                            'replacement' => '', // Удаляем "c"
                            'replace' => $targetChar,
                            'position' => $i, // Позиция символа "c"
                            'length' => 1 // Количество символов для замены
                        ];
                    } else {
                        // Иначе читаем "c" как "к"
                        $replacements[] = [
                            'function' => 'replaceCWithCyrillic',
                            'description' => 'c читается как "к", mascarade  [маскарад], уже упоминаемые нами compote  и cuvette . Но перед тремя гласными е, i и y она читается, как "с". Например: certificat  читается [сертифика], vélocipède  — [велосипед], motocycle  — [мотосикль].',
                            'replacement' => 'к', // Меняем на "к"
                            'replace' => $targetChar,
                            'position' => $i, // Позиция символа "c"
                            'length' => 1 // Количество символов для замены
                        ];
                    }
                } else {
                    // Если "c" не в конце слова, проверяем следующий символ
                    $nextChar = ($i < mb_strlen($word) - 1) ? mb_substr($word, $i + 1, 1) : '';

                    // Если следующий символ - e, i, y или их варианты, заменяем на "с"
                    if (in_array($nextChar, $softeningChars)) {
                        $replacements[] = [
                            'function' => 'replaceCWithCyrillic',
                            'description' => 'c читается как "к", mascarade  [маскарад], уже упоминаемые нами compote  и cuvette . Но перед тремя гласными е, i и y она читается, как "с". Например: certificat  читается [сертифика], vélocipède  — [велосипед], motocycle  — [мотосикль].',
                            'replacement' => 'с', // Меняем на "с"
                            'replace' => $targetChar,
                            'position' => $i, // Позиция символа "c"
                            'length' => 1 // Количество символов для замену
                        ];
                    } else {
                        // Иначе заменяем на "к"
                        $replacements[] = [
                            'function' => 'replaceCWithCyrillic',
                            'description' => 'c читается как "к", mascarade  [маскарад], уже упоминаемые нами compote  и cuvette . Но перед тремя гласными е, i и y она читается, как "с". Например: certificat  читается [сертифика], vélocipède  — [велосипед], motocycle  — [мотосикль].',
                            'replacement' => 'к', // Меняем на "к"
                            'replace' => $targetChar,
                            'position' => $i, // Позиция символа "c"
                            'length' => 1 // Количество символов для замены
                        ];
                    }
                }
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceCedillaWithCyrillic($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'ç';
        $replacementChar = 'с'; // Кириллическая "с"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "ç"
            if ($currentChar === $targetChar) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceCedillaWithCyrillic',
                    'description' => ' ç читается как с.',
                    'replacement' => $replacementChar, // Меняем на "с"
                    'replace' => $targetChar,
                    'position' => $i, // Позиция символа "ç"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function removeSilentH($word)
    {
        // Определяем символ, который нужно обрабатывать
        $targetChar = 'h';

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word); $i++) {
            $currentChar = mb_substr($word, $i, 1);

            // Если текущий символ - "h"
            if ($currentChar === $targetChar) {
                // Проверяем, не является ли "h" частью сочетания "ch" или "ph" или "th"
                $prevChar = ($i > 0) ? mb_substr($word, $i - 1, 1) : '';
                if ($prevChar !== 'c' && $prevChar !== 'p' && $prevChar !== 't') {
                    // Если "h" не входит в "ch" или "ph", удаляем её
                    $replacements[] = [
                        'function' => 'removeSilentH',
                        'description' => 'h не читается НИКОГДА. Как будто ее нет.',
                        'replacement' => '', // Удаляем "h"
                        'replace' => $targetChar,
                        'position' => $i, // Позиция символа "h"
                        'length' => 1 // Количество символов для замены
                    ];
                }
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceChWithCyrillicSh($word)
    {
        // Определяем сочетание, которое нужно обрабатывать
        $targetCombination = 'ch';
        $replacementCombination = 'ш'; // Кириллическое "ш"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            $currentCombination = mb_substr($word, $i, 2);

            // Если текущее сочетание - "ch"
            if ($currentCombination === $targetCombination) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replaceChWithCyrillicSh',
                    'description' => 'Сочетание ch дает звук [ш]. Например chance  [шанс] — удача, везение, chantage  [шантаж], cliché  [клише], cache-nez  [кашне] — шарф (буквально: прячет нос);',
                    'replacement' => $replacementCombination, // Меняем на "ш"
                    'replace' => $targetCombination,
                    'position' => $i, // Позиция сочетания "ch"
                    'length' => 2 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replacePhWithCyrillicF($word)
    {
        // Определяем сочетание, которое нужно обрабатывать
        $targetCombination = 'ph';
        $replacementCombination = 'ф'; // Кириллическое "ф"

        // Массив для хранения результатов замен
        $replacements = [];

        // Проходим по слову
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            $currentCombination = mb_substr($word, $i, 2);

            // Если текущее сочетание - "ph"
            if ($currentCombination === $targetCombination) {
                // Добавляем информацию о замене в массив
                $replacements[] = [
                    'function' => 'replacePhWithCyrillicF',
                    'description' => 'ph читается как "ф"',
                    'replacement' => $replacementCombination, // Меняем на "ф"
                    'replace' => $targetCombination,
                    'position' => $i, // Позиция сочетания "ph"
                    'length' => 2 // Количество символов для замены
                ];
            }
        }

        // Если найдены замены, возвращаем массив
        if (!empty($replacements)) {
            return $replacements;
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private static function replaceTAndTh($word) {
        $results = []; // Массив для хранения всех совпадений
        $wordLength = mb_strlen($word); // Длина слова

        // Проходим по каждому символу слова
        for ($i = 0; $i < $wordLength; $i++) {
            // Проверяем сочетание "th"
            if (mb_substr($word, $i, 2) === 'th') {
                $results[] = [
                    'function' => 'replaceTAndTh',
                    'description' => 'th читается как русское "т"',
                    'replacement' => 'т', // На что меняем
                    'replace' => 'th', // Что меняем
                    'position' => $i, // Позиция символа "th"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
            // Проверяем одиночную букву "t", если она не на конце слова
            elseif ($word[$i] === 't' && $i !== $wordLength - 1) { // Проверяем, что t не в конце слова
                $results[] = [
                    'function' => 'replaceTAndTh',
                    'description' => 't читается как русское "т"',
                    'replacement' => 'т', // На что меняем
                    'replace' => 't', // Что меняем
                    'position' => $i, // Позиция символа "t"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replacePWithCyrillicP($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < strlen($word); $i++) {
            // Проверяем, является ли текущий символ 'p'
            if ($word[$i] === 'p') {
                $results[] = [
                    'function' => 'checkFrenchWordForP', // Название функции
                    'description' => 'p читается как русское "п"', // правило
                    'replacement' => 'п', // На что меняем (русская "п")
                    'replace' => 'p', // Что меняем
                    'position' => $i, // Позиция символа "p"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceJWithCyrillicZh($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < strlen($word); $i++) {
            // Проверяем, является ли текущий символ 'j'
            if ($word[$i] === 'j') {
                $results[] = [
                    'function' => 'replaceJWithCyrillicZh', // Название функции
                    'description' => 'j читается как русское "ж"', // правило
                    'replacement' => 'ж', // На что меняем (русская "ж")
                    'replace' => 'j', // Что меняем
                    'position' => $i, // Позиция символа "j"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceSWithCyrillic($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < strlen($word); $i++) {
            // Проверяем, является ли текущий символ 's'
            if ($word[$i] === 's') {
                // Определяем, как читается 's' в зависимости от контекста
                if ($i > 0 && strpos($vowels, $word[$i - 1]) !== false && strpos($vowels, $word[$i + 1] ?? '') !== false) {
                    // Если 's' находится между двумя гласными, то читается как "з"
                    $replacement = 'з';
                    $description = 's читается как русское "з" между двумя гласными';
                } else {
                    // Иначе читается как "с"
                    $replacement = 'с';
                    $description = 's читается как русское "с"';
                }

                $results[] = [
                    'function' => 'replaceSWithCyrillic', // Название функции
                    'description' => $description, // правило
                    'replacement' => $replacement, // На что меняем
                    'replace' => 's', // Что меняем
                    'position' => $i, // Позиция символа "s"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceNWithCyrillicN($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < strlen($word); $i++) {
            // Проверяем, является ли текущий символ 'n'
            if ($word[$i] === 'n') {
                $results[] = [
                    'function' => 'replaceNWithCyrillicN', // Название функции
                    'description' => 'n читается как русское "н"', // правило
                    'replacement' => 'н', // На что меняем
                    'replace' => 'n', // Что меняем
                    'position' => $i, // Позиция символа "n"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceZWithCyrillicZ($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < strlen($word); $i++) {
            // Проверяем, является ли текущий символ 'z'
            if ($word[$i] === 'z') {
                $results[] = [
                    'function' => 'replaceZWithCyrillicZ', // Название функции
                    'description' => 'z читается как русское "з"', // правило
                    'replacement' => 'з', // На что меняем
                    'replace' => 'z', // Что меняем
                    'position' => $i, // Позиция символа "z"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceAiEiWithCyrillicE($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем сочетание "ai"
            if (mb_substr($word, $i, 2) === 'ai') {
                $results[] = [
                    'function' => 'replaceAiEiWithCyrillicE',
                    'description' => 'ai читается как русское "э"',
                    'replacement' => 'э', // На что меняем
                    'replace' => 'ai', // Что меняем
                    'position' => $i, // Позиция символа "ai"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
            // Проверяем сочетание "ei"
            elseif (mb_substr($word, $i, 2) === 'ei') {
                $results[] = [
                    'function' => 'replaceAiEiWithCyrillicE',
                    'description' => 'ei читается как русское "э"',
                    'replacement' => 'э', // На что меняем
                    'replace' => 'ei', // Что меняем
                    'position' => $i, // Позиция символа "ei"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceEuWithCyrillicYo($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем сочетание "eu"
            if (mb_substr($word, $i, 2) === 'eu') {
                $results[] = [
                    'function' => 'replaceEuWithCyrillicYo',
                    'description' => 'eu читается как русское "ё"',
                    'replacement' => 'ё', // На что меняем
                    'replace' => 'eu', // Что меняем
                    'position' => $i, // Позиция символа "eu"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
            // Проверяем сочетание "œu"
            elseif (mb_substr($word, $i, 3) === 'œu' || mb_substr($word, $i, 3) === 'oeu' ) {
                $results[] = [
                    'function' => 'replaceEuWithCyrillicYo',
                    'description' => 'œu читается как русское "ё"',
                    'replacement' => 'ё', // На что меняем
                    'replace' => 'œu', // Что меняем
                    'position' => $i, // Позиция символа "œu"
                    'length' => 3 // Количество символов для замены
                ];
                $i += 2; // Пропускаем следующие два символа, так как обработали три символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceAuEauWithCyrillicO($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word); $i++) {
            // Проверяем сочетание "au"
            if (mb_substr($word, $i, 2) === 'au') {
                $results[] = [
                    'function' => 'replaceAuEauWithCyrillicO',
                    'description' => 'au читается как русское "о"',
                    'replacement' => 'о', // На что меняем
                    'replace' => 'au', // Что меняем
                    'position' => $i, // Позиция символа "au"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
            // Проверяем сочетание "eau"
            elseif (mb_substr($word, $i, 3) === 'eau') {
                $results[] = [
                    'function' => 'replaceAuEauWithCyrillicO',
                    'description' => 'eau читается как русское "о"',
                    'replacement' => 'о', // На что меняем
                    'replace' => 'eau', // Что меняем
                    'position' => $i, // Позиция символа "eau"
                    'length' => 3 // Количество символов для замены
                ];
                $i += 2; // Пропускаем следующие два символа, так как обработали три символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceOiWithYa($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем сочетание "oi"
            if (mb_substr($word, $i, 2) === 'oi') {
                $results[] = [
                    'function' => 'replaceOiWithYa',
                    'description' => 'oi читается как "ýа" (первый звук близок к белорусской "у-краткой" или английскому [w])',
                    'replacement' => 'ýа', // На что меняем
                    'replace' => 'oi', // Что меняем
                    'position' => $i, // Позиция символа "oi"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceQuWithK($word)
    {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем сочетание "qu"
            if (mb_substr($word, $i, 2) === 'qu') {
                // Определяем, находится ли сочетание "qu" в конце слова
                $isEndOfWord = ($i + 2 === mb_strlen($word)) || !ctype_alpha(mb_substr($word, $i + 2, 1));

                // Если "qu" в конце слова, добавляем информацию о смягчении
                $replacement = $isEndOfWord ? 'кь' : 'к'; // Смягчение на конце слова
                $description = $isEndOfWord
                    ? 'qu читается как "к" с смягчением на конце слова'
                    : 'qu всегда читается как "к"';

                $results[] = [
                    'function' => 'replaceQuWithK',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => 'qu', // Что меняем
                    'position' => $i, // Позиция символа "qu"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceGuWithG($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $vowelsForGu = 'eiyaEIYA'; // Гласные, перед которыми применяется правило

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 2; $i++) {
            // Проверяем сочетание "gu" перед гласными e, i, y, a
            if (mb_substr($word, $i, 2) === 'gu' && isset($word[$i + 2]) && strpos($vowelsForGu, $word[$i + 2]) !== false) {
                $results[] = [
                    'function' => 'replaceGuWithG',
                    'description' => 'gu читается как "г" перед e, i, y, a, при этом u не произносится',
                    'replacement' => 'г', // На что меняем
                    'replace' => 'gu', // Что меняем
                    'position' => $i, // Позиция символа "gu"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceIlWithYOrIy($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв
        $exceptions = ['mille', 'ville', 'tranquille']; // Исключения

        // Проверяем, является ли слово исключением
        if (in_array(mb_strtolower($word), $exceptions)) {
            return null; // Если слово исключение, правило не применяется
        }

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем сочетание "il" или "ill"
            if ((mb_substr($word, $i, 2) === 'il' || mb_substr($word, $i, 3) === 'ill') && isset($word[$i + 2])) {
                // Определяем предыдущий символ (если есть)
                $prevChar = isset($word[$i - 1]) ? $word[$i - 1] : '';

                // Определяем следующий символ после "il(l)"
                $nextChar = isset($word[$i + 2]) ? $word[$i + 2] : '';

                // Правило: если перед "il(l)" стоит гласный, читается как "й"
                if (strpos($vowels, $prevChar) !== false) {
                    $replacement = 'й';
                    $description = 'il(l) читается как "й" после гласного';
                }
                // Правило: если перед "il(l)" стоит согласный или одинокая "u", читается как "ий"
                elseif (ctype_alpha($prevChar) && $prevChar !== 'u' || $prevChar === 'u' && !ctype_alpha($nextChar)) {
                    $replacement = 'ий';
                    $description = 'il(l) читается как "ий" после согласного или одинокой u';
                } else {
                    continue; // Пропускаем, если правило не подходит
                }

                // Добавляем информацию о замене
                $results[] = [
                    'function' => 'replaceIlWithYOrIy',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => mb_substr($word, $i, 2) === 'il' ? 'il' : 'ill', // Что меняем
                    'position' => $i, // Позиция символа "il(l)"
                    'length' => mb_substr($word, $i, 2) === 'il' ? 2 : 3 // Количество символов для замены
                ];

                // Пропускаем обработанные символы
                $i += (mb_substr($word, $i, 2) === 'il' ? 1 : 2);
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceAnWithAn($word) {
        $results = []; // Массив для хранения всех совпадений
        $target = 'an'; // Целевое сочетание
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем, является ли текущая позиция целевым сочетанием
            if (mb_substr($word, $i, 2) === $target) {
                // Определяем следующий символ после сочетания
                $nextChar = isset($word[$i + 2]) ? $word[$i + 2] : '';

                // Проверяем, следует ли за сочетанием гласная или удвоенная n
                if (strpos($vowels, $nextChar) !== false || $nextChar === 'n' && $word[$i + 3] === 'n') {
                    // Если да, носовой звук пропадает, используем "ан"
                    $replacement = 'ан';
                    $description = $target . ' читается как "ан" (носовой звук пропадает)';
                } else {
                    // Иначе сохраняем носовой звук как "ан:"
                    $replacement = 'ан:';
                    $description = $target . ' читается как "ан:"';
                }

                $results[] = [
                    'function' => 'replaceAnWithAn',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => $target, // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceEnWithYnOrAn($word) {
        $results = []; // Массив для хранения всех совпадений
        $target = 'en'; // Целевое сочетание
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проверяем, является ли слово полностью "en"
        if ($word === 'en') {
            $results[] = [
                'function' => 'replaceEnWithYnOrAn',
                'description' => 'en читается как "ан", если это отдельное слово',
                'replacement' => 'ан', // На что меняем
                'replace' => 'en', // Что меняем
                'position' => 0, // Позиция символа
                'length' => 2 // Количество символов для замены
            ];
            return $results;
        }

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем, является ли текущая позиция целевым сочетанием
            if (mb_substr($word, $i, 2) === $target) {
                // Определяем следующий символ после сочетания
                $nextChar = isset($word[$i + 2]) ? $word[$i + 2] : '';

                // Проверяем, следует ли за сочетанием гласная или удвоенная n
                if (strpos($vowels, $nextChar) !== false || $nextChar === 'n' && $word[$i + 3] === 'n') {
                    // Если да, носовой звук пропадает, используем "ён"
                    $replacement = 'ён';
                    $description = $target . ' читается как "ён" (носовой звук пропадает)';
                } else {
                    // Иначе сохраняем носовой звук как "ён:"
                    $replacement = 'ён:';
                    $description = $target . ' читается как "ён:"';
                }

                $results[] = [
                    'function' => 'replaceEnWithYnOrAn',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => $target, // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceAmWithAm($word) {
        $results = []; // Массив для хранения всех совпадений
        $target = 'am'; // Целевое сочетание
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем, является ли текущая позиция целевым сочетанием
            if (mb_substr($word, $i, 2) === $target) {
                // Определяем следующий символ после сочетания
                $nextChar = isset($word[$i + 2]) ? $word[$i + 2] : '';

                // Проверяем, следует ли за сочетанием гласная или удвоенная m
                if (strpos($vowels, $nextChar) !== false || $nextChar === 'm' && $word[$i + 3] === 'm') {
                    // Если да, носовой звук пропадает, используем "ам"
                    $replacement = 'ам';
                    $description = $target . ' читается как "ам" (носовой звук пропадает)';
                } else {
                    // Иначе сохраняем носовой звук как "ам:"
                    $replacement = 'ам:';
                    $description = $target . ' читается как "ам:"';
                }

                $results[] = [
                    'function' => 'replaceAmWithAm',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => $target, // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceEmWithYm($word) {
        $results = []; // Массив для хранения всех совпадений
        $target = 'em'; // Целевое сочетание
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем, является ли текущая позиция целевым сочетанием
            if (mb_substr($word, $i, 2) === $target) {
                // Определяем следующий символ после сочетания
                $nextChar = isset($word[$i + 2]) ? $word[$i + 2] : '';

                // Проверяем, следует ли за сочетанием гласная или удвоенная m
                if (strpos($vowels, $nextChar) !== false || $nextChar === 'm' && $word[$i + 3] === 'm') {
                    // Если да, носовой звук пропадает, используем "ём"
                    $replacement = 'ём';
                    $description = $target . ' читается как "ём" (носовой звук пропадает)';
                } else {
                    // Иначе сохраняем носовой звук как "ём:"
                    $replacement = 'ём:';
                    $description = $target . ' читается как "ём:"';
                }

                $results[] = [
                    'function' => 'replaceEmWithYm',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => $target, // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceOnOmWithOn($word) {
        $results = []; // Массив для хранения всех совпадений
        $targets = ['on', 'om']; // Целевые сочетания
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв
        $consonants = 'bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ'; // Список согласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем, является ли текущая позиция одним из целевых сочетаний
            $currentCombination = mb_substr($word, $i, 2);
            if (in_array($currentCombination, $targets)) {
                // Определяем следующий символ после сочетания
                $nextChar = isset($word[$i + 2]) ? $word[$i + 2] : '';

                // Если следующий символ — согласная, пропускаем обработку
                if (strpos($consonants, $nextChar) !== false) {
                    continue; // Пропускаем, если после on/om идет согласная
                }

                // Проверяем, следует ли за сочетанием гласная или удвоенная n/m
                if (strpos($vowels, $nextChar) !== false || $nextChar === 'n' && $word[$i + 3] === 'n' || $nextChar === 'm' && $word[$i + 3] === 'm') {
                    // Если да, носовой звук пропадает, используем "он"
                    $replacement = 'он';
                    $description = $currentCombination . ' читается как "он" (носовой звук пропадает)';
                } else {
                    // Иначе сохраняем носовой звук как "он:"
                    $replacement = 'он:';
                    $description = $currentCombination . ' читается как "он:" (носовое "н")';
                }

                $results[] = [
                    'function' => 'replaceOnOmWithOn',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => $currentCombination, // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceInUnAinEinYnWithEn($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $targets = ['in', 'un', 'ain', 'ein', 'yn']; // Целевые сочетания
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word); $i++) {
            // Проверяем, является ли текущая позиция одним из целевых сочетаний
            foreach ($targets as $target) {
                if (mb_substr($word, $i, mb_strlen($target)) === $target) {
                    // Определяем следующий символ после сочетания
                    $nextChar = isset($word[$i + mb_strlen($target)]) ? $word[$i + mb_strlen($target)] : '';

                    // Проверяем, следует ли за сочетанием гласная или удвоенная n/m
                    if (strpos($vowels, $nextChar) !== false || $nextChar === 'n' && $word[$i + mb_strlen($target) + 1] === 'n' || $nextChar === 'm' && $word[$i + mb_strlen($target) + 1] === 'm') {
                        // Если да, носовой звук пропадает, используем "ен"
                        $replacement = 'ен';
                        $description = $target . ' читается как "ен" (носовой звук пропадает)';
                    } else {
                        // Иначе сохраняем носовой звук как "эн:"
                        $replacement = 'эн:';
                        $description = $target . ' читается как "эн:"';
                    }

                    $results[] = [
                        'function' => 'replaceInUnAinEinYnWithEn',
                        'description' => $description,
                        'replacement' => $replacement, // На что меняем
                        'replace' => $target, // Что меняем
                        'position' => $i, // Позиция символа
                        'length' => mb_strlen($target) // Количество символов для замены
                    ];
                    $i += mb_strlen($target) - 1; // Пропускаем обработанные символы
                    break; // Переходим к следующей позиции после обработки
                }
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceImUmAimYmWithEm($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $targets = ['im', 'um', 'aim', 'ym']; // Целевые сочетания
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word); $i++) {
            // Проверяем, является ли текущая позиция одним из целевых сочетаний
            foreach ($targets as $target) {
                if (mb_substr($word, $i, mb_strlen($target)) === $target) {
                    // Определяем следующий символ после сочетания
                    $nextChar = isset($word[$i + mb_strlen($target)]) ? $word[$i + mb_strlen($target)] : '';

                    // Проверяем, следует ли за сочетанием гласная или удвоенная n/m
                    if (strpos($vowels, $nextChar) !== false || $nextChar === 'n' && $word[$i + mb_strlen($target) + 1] === 'n' || $nextChar === 'm' && $word[$i + mb_strlen($target) + 1] === 'm') {
                        // Если да, носовой звук пропадает, используем "ем"
                        $replacement = 'ем';
                        $description = $target . ' читается как "ем" (носовой звук пропадает)';
                    } else {
                        // Иначе сохраняем носовой звук как "эм:"
                        $replacement = 'эм:';
                        $description = $target . ' читается как "эм:"';
                    }

                    $results[] = [
                        'function' => 'replaceImUmAimYmWithEm',
                        'description' => $description,
                        'replacement' => $replacement, // На что меняем
                        'replace' => $target, // Что меняем
                        'position' => $i, // Позиция символа
                        'length' => mb_strlen($target) // Количество символов для замены
                    ];
                    $i += mb_strlen($target) - 1; // Пропускаем обработанные символы
                    break; // Переходим к следующей позиции после обработки
                }
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceTiWithCOrT($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 2; $i++) {
            // Проверяем, является ли текущая позиция сочетанием "ti"
            if (mb_substr($word, $i, 2) === 'ti' && isset($word[$i + 2]) && strpos($vowels, $word[$i + 2]) !== false) {
                // Определяем предыдущий символ
                $prevChar = isset($word[$i - 1]) ? $word[$i - 1] : '';

                if ($prevChar === 's') {
                    // Если перед "ti" стоит "s", t читается как "т"
                    $replacement = 'т';
                    $description = 'ti читается как "т" перед гласной, если перед ti стоит s';
                } else {
                    // Иначе ti читается как "си"
                    $replacement = 'си';
                    $description = 'ti читается как "си" перед гласной';
                }

                $results[] = [
                    'function' => 'replaceTiWithCOrT',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => 'ti', // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceXWithRules($word)
    {
        $results = []; // Массив для хранения всех совпадений
        $vowels = 'aeiouyAEIOUY'; // Список гласных букв

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word); $i++) {
            if ($word[$i] === 'x') {
                // Проверяем контекст: начало слова + "ex-" перед гласной
                if ($i > 0 && mb_substr($word, $i - 1, 2) === 'ex' && isset($word[$i + 1]) && strpos($vowels, $word[$i + 1]) !== false) {
                    $replacement = 'гз';
                    $description = 'x в словах, начинающихся с ex- перед гласными читается как "гз"';

                    // Проверяем контекст: начало слова + "ex-" перед согласной
                } elseif ($i > 0 && mb_substr($word, $i - 1, 2) === 'ex' && isset($word[$i + 1]) && strpos($vowels, $word[$i + 1]) === false) {
                    $replacement = 'кс';
                    $description = 'x в словах, начинающихся с ex- перед согласными читается как "кс"';

                    // Проверяем контекст: x в середине слова
                } elseif ($i > 0 && $i < mb_strlen($word) - 1) {
                    $replacement = 'кс';
                    $description = 'x в середине слова читается как "кс"';

                    // Проверяем контекст: порядковые числительные
                } elseif (preg_match('/(deuxième|sixième)/iu', $word)) {
                    $replacement = 'з';
                    $description = 'x в порядковых числительных читается как "з"';
                } else {
                    continue; // Пропускаем, если правило не подходит
                }

                $results[] = [
                    'function' => 'replaceXWithRules',
                    'description' => $description,
                    'replacement' => $replacement, // На что меняем
                    'replace' => 'x', // Что меняем
                    'position' => $i, // Позиция символа
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private function replaceFWithF($word) {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word); $i++) {
            // Проверяем, является ли текущий символ 'f'
            if ($word[$i] === 'f') {
                $results[] = [
                    'function' => 'replaceFWithF',
                    'description' => 'f читается как русское "ф"',
                    'replacement' => 'ф', // На что меняем
                    'replace' => 'f', // Что меняем
                    'position' => $i, // Позиция символа "f"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private function replaceBWithB($word) {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word); $i++) {
            // Проверяем, является ли текущий символ 'b'
            if ($word[$i] === 'b') {
                $results[] = [
                    'function' => 'replaceBWithB',
                    'description' => 'b читается как русское "б"',
                    'replacement' => 'б', // На что меняем
                    'replace' => 'b', // Что меняем
                    'position' => $i, // Позиция символа "b"
                    'length' => 1 // Количество символов для замены
                ];
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private function replaceNnWithN($word) {
        $results = []; // Массив для хранения всех совпадений

        // Проходим по каждому символу слова
        for ($i = 0; $i < mb_strlen($word) - 1; $i++) {
            // Проверяем, является ли текущая позиция сочетанием 'nn'
            if (mb_substr($word, $i, 2) === 'nn') {
                $results[] = [
                    'function' => 'replaceNnWithN',
                    'description' => 'nn читается как одно "н"',
                    'replacement' => 'н', // На что меняем
                    'replace' => 'nn', // Что меняем
                    'position' => $i, // Позиция символа "nn"
                    'length' => 2 // Количество символов для замены
                ];
                $i++; // Пропускаем следующий символ, так как обработали два символа
            }
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private static function replaceNnementWithNyeMye($word) {
        $results = []; // Массив для хранения всех совпадений
        $target = 'nnement'; // Целевое сочетание

        // Проверяем, заканчивается ли слово на "nnement"
        if (mb_substr($word, -7) === $target) {
            $results[] = [
                'function' => 'replaceNnementWithNyeMye',
                'description' => 'nnement на конце слова читается как "нёмё"',
                'replacement' => 'нёмё', // На что меняем
                'replace' => $target, // Что меняем
                'position' => mb_strlen($word) - 7, // Позиция символа "nnement"
                'length' => 7 // Количество символов для замены
            ];
        }

        // Если ничего не найдено, возвращаем null
        return !empty($results) ? $results : null;
    }

    private function replaceLleEndingWithL(string $word): ?array
    {
        $targetCombination = 'lle'; // Целевое сочетание
        $replacementCombination = 'ль';

        // Проверяем, заканчивается ли слово на "lle"
        if (mb_substr($word, -3) === $targetCombination) {
            return [[
                'function' => 'replaceLleWithLy',
                'description' => 'lle на конце слова читается как "ль"',
                'replacement' => $replacementCombination, // На что меняем
                'replace' => $targetCombination, // Что меняем
                'position' => mb_strlen($word) - 3, // Позиция символа "lle"
                'length' => 3 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private function replaceDWithD(string $word): ?array
    {
        // Находим все позиции буквы 'd' в слове
        $results = [];
        $position = 0;

        // Ищем каждое вхождение 'd'
        while (($position = mb_strpos($word, 'd', $position)) !== false) {
            $results[] = [
                'function' => 'replaceDWithD',
                'description' => 'буква "d" читается как "д"',
                'replacement' => 'д', // На что меняем
                'replace' => 'd', // Что меняем
                'position' => $position, // Позиция символа "d"
                'length' => 1 // Количество символов для замены
            ];

            // Перемещаем позицию для поиска следующего вхождения
            $position += 1;
        }

        // Если ничего не найдено, возвращаем null
        if (empty($results)) {
            return null;
        }

        return $results;
    }

    private function replaceMWithM(string $word): ?array {
        // Находим все позиции буквы 'm' в слове
        $results = [];
        $position = 0;

        // Ищем каждое вхождение 'm'
        while (($position = mb_strpos($word, 'm', $position)) !== false) {
            $results[] = [
                'function' => 'replaceMWithM',
                'description' => 'буква "m" читается как "м"',
                'replacement' => 'м', // На что меняем
                'replace' => 'm', // Что меняем
                'position' => $position, // Позиция символа "m"
                'length' => 1 // Количество символов для замены
            ];

            // Перемещаем позицию для поиска следующего вхождения
            $position += 1;
        }

        // Если ничего не найдено, возвращаем null
        if (empty($results)) {
            return null;
        }

        return $results;
    }

    private function replaceTtreEndingWithTr(string $word): ?array {
        // Проверяем, заканчивается ли слово на 'ttre'
        if (mb_substr($word, -4) === 'ttre') {
            return [[
                'function' => 'replaceTtreEndingWithTr',
                'description' => 'ttre на конце слова читается как "тр"',
                'replacement' => 'тр', // На что меняем
                'replace' => 'ttre', // Что меняем
                'position' => mb_strlen($word) - 4, // Позиция символа "ttre"
                'length' => 4 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private function replaceErEndingWithE(string $word): ?array
    {
        // Проверяем, заканчивается ли слово на 'er'
        if (mb_substr($word, -2) === 'er') {
            return [[
                'function' => 'replaceErEndingWithE',
                'description' => 'er на конце слова читается как "е"',
                'replacement' => 'е', // На что меняем
                'replace' => 'er', // Что меняем
                'position' => mb_strlen($word) - 2, // Позиция символа "er"
                'length' => 2 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private function replaceAllCcWithK(string $word): ?array
    {
        // Находим все позиции подстроки 'cc' в слове
        $results = [];
        $position = 0;

        // Ищем каждое вхождение 'cc'
        while (($position = mb_strpos($word, 'cc', $position)) !== false) {
            $results[] = [
                'function' => 'replaceAllCcWithK',
                'description' => 'cc читается как "к"',
                'replacement' => 'к', // На что меняем
                'replace' => 'cc', // Что меняем
                'position' => $position, // Позиция символа "cc"
                'length' => 2 // Количество символов для замены
            ];

            // Перемещаем позицию для поиска следующего вхождения
            $position += 2; // Длина 'cc' равна 2
        }

        // Если ничего не найдено, возвращаем null
        if (empty($results)) {
            return null;
        }

        return $results;
    }

    private function replaceReEndingWithR(string $word): ?array
    {
        // Проверяем, заканчивается ли слово на 're'
        if (mb_substr($word, -2) === 're') {
            return [[
                'function' => 'replaceReEndingWithR',
                'description' => 're на конце слова читается как "р"',
                'replacement' => 'р', // На что меняем
                'replace' => 're', // Что меняем
                'position' => mb_strlen($word) - 2, // Позиция символа "re"
                'length' => 2 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private function replaceRWithR(string $word): ?array
    {
        // Находим все позиции буквы 'r' в слове
        $results = [];
        $position = 0;

        // Ищем каждое вхождение 'r'
        while (($position = mb_strpos($word, 'r', $position)) !== false) {
            $results[] = [
                'function' => 'replaceRWithR',
                'description' => 'буква "r" читается как "р"',
                'replacement' => 'р', // На что меняем
                'replace' => 'r', // Что меняем
                'position' => $position, // Позиция символа "r"
                'length' => 1 // Количество символов для замены
            ];

            // Перемещаем позицию для поиска следующего вхождения
            $position += 1;
        }

        // Если ничего не найдено, возвращаем null
        if (empty($results)) {
            return null;
        }

        return $results;
    }

    private function replaceCEndingWithEmpty(string $word): ?array
    {
        // Проверяем, заканчивается ли слово на 'c'
        if (mb_substr($word, -1) === 's') {
            return [[
                'function' => 'replaceCEndingWithEmpty',
                'description' => 'буква "s" на конце слова не читается',
                'replacement' => '', // На что меняем (пустая строка, так как буква не читается)
                'replace' => 's', // Что меняем
                'position' => mb_strlen($word) - 1, // Позиция символа "c"
                'length' => 1 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }

    private function replaceCieWithSy(string $word): ?array
    {
        // Находим все позиции подстроки 'cie' в слове
        $results = [];
        $position = 0;

        // Ищем каждое вхождение 'cie'
        while (($position = mb_strpos($word, 'cie', $position)) !== false) {
            $results[] = [
                'function' => 'replaceCieWithSy',
                'description' => 'cie читается как "сье"',
                'replacement' => 'сье', // На что меняем
                'replace' => 'cie', // Что меняем
                'position' => $position, // Позиция символа "cie"
                'length' => 3 // Количество символов для замены
            ];

            // Перемещаем позицию для поиска следующего вхождения
            $position += 3; // Длина 'cie' равна 3
        }

        // Если ничего не найдено, возвращаем null
        if (empty($results)) {
            return null;
        }

        return $results;
    }

    private function replaceNneEndingWithN(string $word): ?array
    {
        // Проверяем, заканчивается ли слово на 'nne'
        if (mb_substr($word, -3) === 'nne') {
            return [[
                'function' => 'replaceNneEndingWithN',
                'description' => 'nne на конце слова читается как "н"',
                'replacement' => 'н', // На что меняем
                'replace' => 'nne', // Что меняем
                'position' => mb_strlen($word) - 3, // Позиция символа "nne"
                'length' => 3 // Количество символов для замены
            ]];
        }

        // Если правило не подходит, возвращаем null
        return null;
    }
}
