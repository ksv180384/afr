<?php

namespace App\Services\App;

use App\Models\Lesson;

class LessonService
{
    /**
     * Получает запись из БД по id
     * @param int $id - идентификатор записи
     * @return mixed
     */
    public function getById(int $id)
    {
        $lesson = Lesson::where('id', '=', $id)
            ->first(['id', 'title', 'description', 'content']);

        return $lesson;
    }

    /**
     * Получает все названия
     * @return mixed
     */
    public function getTitlesList()
    {
        $lessonsTitle = Lesson::all(['id', 'title']);

        return $lessonsTitle;
    }
}
