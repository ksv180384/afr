<?php

namespace App\Services\Admin\Grammar;

use App\Models\Grammar;
use Illuminate\Database\Eloquent\Collection;

class GrammarService
{
    public function getGrammars(): Collection
    {
        $grammars = Grammar::query()->get(['id', 'title']);

        return  $grammars;
    }

    /**
     * @param int $id
     * @return Grammar|null
     */
    public function getById(int $id): ?Grammar
    {
        $grammar = Grammar::query()->findOrFail($id);

        return  $grammar;
    }

    /**
     * @param array $grammarData
     * @return Grammar
     */
    public function create(array $grammarData): Grammar
    {
        $grammar = Grammar::query()->create($grammarData);

        return $grammar;
    }

    /**
     * @param int $id
     * @param array $grammarData
     * @return Grammar
     */
    public function update(int $id, array $grammarData): Grammar
    {
        $grammar = Grammar::query()->findOrFail($id);

        $grammar->update($grammarData);

        return $grammar;
    }
}
