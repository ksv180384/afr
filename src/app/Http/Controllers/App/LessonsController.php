<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\App\LessonService;
use Inertia\Inertia;

class LessonsController extends Controller
{
    public function index(LessonService $lessonService)
    {
        $authUser = Helper::getUserData();
        $lessons = $lessonService->getTitlesList();

        return Inertia::render('Lessons/Lessons', [
            'authUser' => $authUser,
            'menu' => $lessons,
        ]);
    }

    public function show(int $id, LessonService $lessonService)
    {
        $authUser = Helper::getUserData();
        $lessons = $lessonService->getTitlesList();
        $lesson = $lessonService->getById($id);

        return Inertia::render('Lessons/LessonShow', [
            'authUser' => $authUser,
            'menu' => $lessons,
            'lessonContent' => $lesson,
        ]);
    }
}
