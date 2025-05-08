<?php

namespace App\Services\Admin\Lesson;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Collection;

class LessonService
{

    /**
     * @return Collection
     */
    public function getLessons(): Collection
    {
         $lessons = Lesson::query()
            ->select([
                'id',
                'title',
            ])
            ->get();

        return  $lessons;
    }

    /**
     * @param int $id
     * @return Lesson|null
     */
    public function getById(int $id): ?Lesson
    {
        $lesson = Lesson::query()->findOrFail($id);

        return  $lesson;
    }

    /**
     * @param array $lessonData
     * @return Lesson
     */
    public function create(array $lessonData): Lesson
    {
        $lesson = Lesson::query()->create($lessonData);

        return $lesson;
    }

    /**
     * @param int $id
     * @param array $lessonData
     * @return Lesson
     */
    public function update(int $id, array $lessonData): Lesson
    {
        $lesson = Lesson::query()->findOrFail($id);

        $lesson->update($lessonData);

        return $lesson;
    }
}
