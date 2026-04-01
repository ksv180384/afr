<?php

namespace App\Services\App;

use App\Models\Lesson;

class LessonService
{
    public function getById(int $id)
    {
        return Lesson::where('id', '=', $id)
            ->first(['id', 'title', 'description', 'content']);
    }

    public function getTitlesList()
    {
        return Lesson::all(['id', 'title']);
    }
}
