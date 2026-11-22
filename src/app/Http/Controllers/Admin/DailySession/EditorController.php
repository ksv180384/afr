<?php

namespace App\Http\Controllers\Admin\DailySession;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DailySession\EditorRequest;
use App\Http\Resources\PaginateResource;
use App\Models\Learning\LearningContentReport;
use App\Models\Learning\LearningMedia;
use App\Models\Learning\LearningSession;
use App\Services\Learning\ContentEditorService;
use App\Services\Learning\MediaLibraryService;
use App\Services\Learning\SessionExportService;
use App\Services\Learning\SessionPackageValidator;
use App\Services\Learning\TopicBackgroundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EditorController extends Controller
{
    public function renameSession(EditorRequest $request, int $session, \App\Services\Learning\PracticeUrls $urls)
    {
        return response()->json($urls->rename($request->user(), 'session', $session, $request->validated()));
    }

    public function renameUnit(EditorRequest $request, int $unit, \App\Services\Learning\PracticeUrls $urls)
    {
        return response()->json($urls->rename($request->user(), 'unit', $unit, $request->validated()));
    }

    public function uploadUnitBackground(EditorRequest $request, int $unit, TopicBackgroundService $backgrounds): JsonResponse
    {
        $topic = $backgrounds->replace($request->user(), $unit, $request->file('file'));

        return response()->json(['background_url' => $topic->background_url]);
    }

    public function index(EditorRequest $request, ContentEditorService $editor): Response
    {
        $versions = $editor->versions($request->validated());

        return Inertia::render('DailySession/Index', ['authUser' => Helper::getUserData(),
            'versions' => $versions->getCollection()->map(fn ($version) => $editor->versionRow($version)), 'paginate' => [...PaginateResource::make($versions)->resolve(), 'prev_page_url' => $versions->previousPageUrl(), 'next_page_url' => $versions->nextPageUrl()],
            'filters' => $request->validated(), 'levels' => config('daily_practice.levels')]);
    }

    public function imports(EditorRequest $request): Response
    {
        return Inertia::render('DailySession/Imports', ['authUser' => Helper::getUserData()]);
    }

    public function show(EditorRequest $request, int $version, ContentEditorService $editor, SessionPackageValidator $validator, SessionExportService $export): Response
    {
        return Inertia::render('DailySession/Show', ['authUser' => Helper::getUserData(), ...$editor->detail($request->user(), $version, $validator, $export)]);
    }

    public function legacyLesson(EditorRequest $request, int $version, string $action = 'show'): RedirectResponse
    {
        $lesson = LearningSession::query()->where('legacy_content_id', $version)->firstOrFail(['id']);

        return redirect()->route('admin.daily-practice.versions.'.$action, [...$request->query(), 'version' => $lesson->id]);
    }

    public function preview(EditorRequest $request, int $version, ContentEditorService $editor, SessionPackageValidator $validator, SessionExportService $export): Response
    {
        $detail = $editor->detail($request->user(), $version, $validator, $export);

        return Inertia::render('DailySession/Preview', ['authUser' => Helper::getUserData(), 'version' => $detail['version'],
            'audioUrls' => $detail['audioUrls'], 'frame' => $request->boolean('frame'), 'scenario' => $request->validated('scenario', 'session')]);
    }

    public function catalog(EditorRequest $request, ContentEditorService $editor): Response
    {
        return Inertia::render('DailySession/Catalog', ['authUser' => Helper::getUserData(), 'filters' => $request->validated(), ...$editor->catalog($request->validated())]);
    }

    public function reports(EditorRequest $request): Response
    {
        $query = LearningContentReport::query()->with('session:id,external_id');
        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        return Inertia::render('DailySession/Reports', ['authUser' => Helper::getUserData(), 'reports' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $request->validated()]);
    }

    public function storeReport(EditorRequest $request, ContentEditorService $editor): \Illuminate\Http\JsonResponse
    {
        return response()->json($editor->storeReport($request->user(), $request->validated()), 201);
    }

    public function updateReport(EditorRequest $request, int $report, ContentEditorService $editor): \Illuminate\Http\JsonResponse
    {
        return response()->json($editor->updateReport($request->user(), $report, $request->validated()));
    }

    public function media(EditorRequest $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.daily-practice.index');
    }

    public function storeMedia(EditorRequest $request, MediaLibraryService $library): \Illuminate\Http\JsonResponse
    {
        return response()->json($library->upload($request->user(), $request->file('file')), 201);
    }

    public function approveMedia(EditorRequest $request, string $media, MediaLibraryService $library): \Illuminate\Http\JsonResponse
    {
        return response()->json($library->approve($request->user(), $media));
    }

    public function mediaFile(EditorRequest $request, string $media, MediaLibraryService $library): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $asset = LearningMedia::query()->findOrFail($media);

        return response()->file($library->file($asset), ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
