<?php

namespace App\Services\App;


use App\Models\Proverb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProverbService {

    const PROVERB_PAGINATION = 20;

    public function getById(int $id): Proverb
    {
        $proverb = Proverb::query()->select(['id', 'text', 'translation'])->find($id);

        return $proverb;
    }

    /**
     * @param $count
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProverbsPagination($count = 10): LengthAwarePaginator
    {
        $proverbs = Proverb::query()
            ->select(['id', 'text', 'translation'])
            ->orderBy('id')
            ->paginate($count);

        return $proverbs;
    }

    /**
     * Получает заданное количество случайных пословиц
     * @param int $count
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function proverbRandom($count = 1): Collection
    {
        $proverbs = Proverb::query()
            ->select(['id', 'text', 'translation'])
            ->inRandomOrder()
            ->limit($count)
            ->get();

        return $proverbs;
    }

    /**
     * Получает случайную пословицу
     * @return Proverb
     */
    public function proverbRandomOne(): Proverb
    {
        $proverb = Proverb::select(['id', 'text', 'translation'])
            ->inRandomOrder()
            ->first();

        return $proverb;
    }

    /**
     * Добавляем пословицу
     * @param array $proverbData
     * @return Proverb
     */
    public function create(array $proverbData): Proverb
    {
        $proverb = Proverb::query()->create($proverbData);

        return $proverb;
    }

    /**
     * Редактируем пословицу
     * @param int $id
     * @param array $proverbData
     * @return Proverb
     */
    public function update(int $id, array $proverbData): Proverb
    {
        $proverb = Proverb::query()->findOrFail($id);
        $proverb->update($proverbData);

        return $proverb;
    }
}
