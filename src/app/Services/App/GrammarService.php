<?php

namespace App\Services\App;

use App\Models\Grammar;

class GrammarService
{
    public function getTitles()
    {
        return Grammar::all(['id', 'title']);
    }

    public function getById(int $id, $fields = ['id', 'title', 'description', 'content'])
    {
        return Grammar::findOrFail($id)->only($fields);
    }
}
