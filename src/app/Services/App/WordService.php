<?php

namespace App\Services\App;

use App\Filters\Admin\DictionaryFilter;
use App\Filters\WordFilters;
use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class WordService {

    const PAGINATE = 90;

    /**
     * @param int $id
     * @return Word
     */
    public function getById(int $id): Word
    {
        $word = Word::query()->select([
            'id',
            'word',
            'translation',
            'transcription',
            'example',
        ])->findOrFail($id);

        return $word;
    }

    /**
     * Получает заданное количество случайных слов
     * @param int $count
     * @param array $columns
     * @return Collection
     */
    public function wordsRandom(
        array $columns = ['id', 'word', 'translation', 'transcription', 'example'],
        int $count = 10
    ): Collection
    {
        $words = Word::query()
            ->inRandomOrder()
            ->limit($count)
            ->get($columns);

        return $words;
    }

    /**
     * Поиск слова ru
     * @param string $search_text
     * @return Collection
     */
    public function searchRu(string $search_text): Collection
    {
        $wordsList = Word::select(['id', 'word', 'translation'])
            ->where('translation', 'LIKE', '%' . $search_text . '%')
            ->limit(10)
            ->get();

        return $wordsList;
    }

    /**
     * Поиск слова fr
     * @param string $search_text
     * @return Collection
     */
    public function searchFr(string $search_text): Collection
    {
        $search_text = preg_replace("#\b(la |le |les |un |une |se )#", "", $search_text);
        $wordsList = Word::select(['id', 'word', 'translation'])
            ->where('word', 'LIKE', '%' . $search_text . '%')
            ->limit(10)
            ->get();

        return $wordsList;
    }

    /**
     * @param string $searchText
     * @param string $lang
     * @return Collection
     */
    public function search(string $searchText, string $lang = 'fr'): Collection
    {
        $resultFields = ['id', 'word', 'translation', 'transcription', 'example'];
        if($lang === 'fr'){
            $searchText = preg_replace("#\b(la |le |les |un |une |se )#", "", $searchText);
            $wordsList = Word::where('word', 'LIKE', '%' . $searchText . '%')->get($resultFields);
        }else{
            $wordsList = Word::where('translation', 'LIKE', '%' . $searchText . '%')->get($resultFields);
            $wordsList = $wordsList->map(function ($item){
                $word = $item->word;
                $item->word = $item->translation;
                $item->translation = $word;
                return $item;
            });
        }

        return $wordsList;
    }

    /**
     * @param string $search_text
     * @return Collection
     */
    public function searchRuPage(string $search_text): Collection
    {
        $wordsList = Word::select(['id', 'word', 'translation', 'example'])
            ->where('translation', 'LIKE', '%' . $search_text . '%')
            ->limit(10)
            ->get();

        return $wordsList;
    }

    /**
     * @param string $search_text
     * @return Collection
     */
    public function searchFrPage(string $search_text): Collection
    {
        $search_text = preg_replace("#\b(la |le |les |un |une |se )#", "", $search_text);
        $wordsList = Word::select(['id', 'word', 'translation', 'example'])
            ->where('word', 'LIKE', '%' . $search_text . '%')
            ->limit(10)
            ->get();

        return $wordsList;
    }

    /**
     * @param int|null $partsOfSpeech
     * @param string $lang
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getWordsPaginate(int $partsOfSpeech = null, string $lang = 'fr'): LengthAwarePaginator
    {

        $filter = new WordFilters(request());

        $words = Word::query()
            ->select([
                'words.id',
                'words.word',
                'words.example',
                'words.translation',
                'words.transcription',
                'words.id_part_of_speech',
            ])
            ->with(['part_of_speech:id,title'])
            ->filter($filter)
            ->when($partsOfSpeech, function ($q) use($partsOfSpeech) {
                $q->where('id_part_of_speech', '=', $partsOfSpeech);
            })
            ->when($lang === 'ru', function ($q){
                $q->orderBy('translation', 'ASC');
            }, function ($q){
                $q->orderBy('word', 'ASC');
            })
            ->paginate(self::PAGINATE);

        return $words;
    }

    /**
     * Из массива слов создаем массив слов для теста (слово, и массив слов как варианты ответов)
     * ['answer' => 'слово которое можно угадать', 'answer_options' => ['массив предлагаемых вариантов']]
     * @param array $words - массив слов
     * @param int $countAnswerOptions - количество вариантов ответа на один вопрос
     * @return array
     */
    public function wordsListToWordsTestYourSelf(array $words, int $countAnswerOptions = 5): array
    {
        $result = [];
        $countAnswers = count($words) / $countAnswerOptions;
        for ($i = 0; $countAnswers > $i; $i++){
            $answerOptions = array_slice($words, ($i * $countAnswerOptions), $countAnswerOptions);
            $result[$i] = [
                'answer' => $answerOptions[0],
                'answer_options' => $answerOptions,
            ];
            shuffle($result[$i]['answer_options']);
        }

        return $result;
    }

    /**
     * @return Collection
     */
    public function searchFrAll(): Collection
    {
        $filter = new DictionaryFilter(request());

        $words = Word::query()
            ->filter($filter)
            ->get(['id', 'word', 'translation', 'transcription', 'example']);

        return $words;
    }
}
