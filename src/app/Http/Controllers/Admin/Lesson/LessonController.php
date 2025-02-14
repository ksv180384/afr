<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\CreateLessonRequest;
use App\Http\Requests\Admin\Lesson\UpdateLessonRequest;
use App\Http\Resources\Admin\Lesson\LessonEditResource;
use App\Http\Resources\Admin\Lesson\LessonResource;
use App\Services\Admin\Lesson\LessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * @param LessonService $lessonService
     * @return Response
     */
    public function index(LessonService $lessonService): Response
    {
        $authUser = Helper::getUserData();
        $lessons = $lessonService->getLessons();

        return Inertia::render('Lesson/Lessons', [
            'authUser' => $authUser,
            'lessons' => LessonResource::collection($lessons),
        ]);
    }

    /**
     * @return Response
     */
    public function create(): Response
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Lesson/LessonCreate', [
            'authUser' => $authUser,
        ]);
    }

    /**
     * @param CreateLessonRequest $request
     * @param LessonService $lessonService
     * @return RedirectResponse
     */
    public function store(CreateLessonRequest $request, LessonService $lessonService): RedirectResponse
    {
        $lessonData = $request->validated();

        $lesson = $lessonService->create($lessonData);

        return Redirect::route('admin.lessons.edit', ['id' => $lesson->id]);
    }

    public function edit(int $id, LessonService $lessonService): Response
    {
        $authUser = Helper::getUserData();
        $lesson = $lessonService->getById($id);

        return Inertia::render('Lesson/LessonEdit', [
            'authUser' => $authUser,
            'lesson' => LessonEditResource::make($lesson),
        ]);
    }

    /**
     * @param int $id
     * @param UpdateLessonRequest $request
     * @param LessonService $lessonService
     * @return RedirectResponse
     */
    public function update(int $id, UpdateLessonRequest $request, LessonService $lessonService): RedirectResponse
    {
        $lessonData = $request->validated();

        $lesson = $lessonService->update($id, $lessonData);

        return Redirect::route('admin.lessons.edit', ['id' => $lesson->id]);
    }

    public function delete()
    {

    }
}
