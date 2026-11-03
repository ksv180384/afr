<?php

namespace App\Http\Controllers\Admin\DailySession;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DailySession\ContentOperationRequest;
use App\Http\Requests\Admin\DailySession\UploadSessionPackageRequest;
use App\Services\Learning\CatalogPublicationService;
use App\Services\Learning\CurriculumPublicationService;
use App\Services\Learning\SessionExportService;
use App\Services\Learning\SessionImportService;
use App\Services\Learning\SessionPublicationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ContentController extends Controller
{
    public function preview(UploadSessionPackageRequest $request, SessionImportService $service): JsonResponse
    {
        return response()->json([...$service->preview($request->user(), $request->file('file')), 'plan' => app(\App\Services\Learning\ContentEditorService::class)->importPlan($request->packageReport()->package())], 201)->header('Cache-Control', 'no-store');
    }

    public function commit(ContentOperationRequest $request, string $batch, SessionImportService $service): JsonResponse
    {
        return response()->json($service->commit($request->user(), $batch, $request->validated('token'), $request->validated('file_hash')));
    }

    public function reviewVersion(ContentOperationRequest $request, int $version, SessionPublicationService $service): JsonResponse
    {
        return response()->json($service->review($request->user(), $version, $request->checks(), $request->validated('notes')));
    }

    public function publishVersion(ContentOperationRequest $request, int $version, SessionPublicationService $service): JsonResponse
    {
        return response()->json($service->publish($request->user(), $version));
    }

    public function publishMany(ContentOperationRequest $request, SessionPublicationService $service): JsonResponse
    {
        return response()->json($service->publishMany($request->user(), $request->validated('version_ids')));
    }

    public function archiveVersion(ContentOperationRequest $request, int $version, SessionPublicationService $service): JsonResponse
    {
        return response()->json($service->archiveVersion($request->user(), $version));
    }

    public function changeStatus(ContentOperationRequest $request, SessionPublicationService $service): JsonResponse
    {
        return response()->json($service->changeStatus($request->user(), $request->validated('version_ids'), $request->validated('status'), $request->safe()->only(['notes'])));
    }

    public function publishCatalogDirect(ContentOperationRequest $request, int $revision, CatalogPublicationService $service): JsonResponse
    {
        return response()->json($service->publish($request->user(), $revision, false));
    }

    public function publishCurriculumDirect(ContentOperationRequest $request, int $curriculum, CurriculumPublicationService $service): JsonResponse
    {
        return response()->json($service->publish($request->user(), $curriculum, false));
    }

    public function archiveSessions(ContentOperationRequest $request, SessionPublicationService $service): JsonResponse
    {
        return response()->json($service->archive($request->user(), $request->validated('session_ids')));
    }

    public function diff(ContentOperationRequest $request, int $revision, CatalogPublicationService $service): JsonResponse
    {
        return response()->json($service->diff($request->user(), $revision));
    }

    public function reviewCatalog(ContentOperationRequest $request, int $revision, CatalogPublicationService $service): JsonResponse
    {
        return response()->json($service->review($request->user(), $revision, $request->checks()));
    }

    public function publishCatalog(ContentOperationRequest $request, int $revision, CatalogPublicationService $service): JsonResponse
    {
        return response()->json($service->publish($request->user(), $revision));
    }

    public function reviewCurriculum(ContentOperationRequest $request, int $curriculum, CurriculumPublicationService $service): JsonResponse
    {
        return response()->json($service->review($request->user(), $curriculum, $request->checks()));
    }

    public function publishCurriculum(ContentOperationRequest $request, int $curriculum, CurriculumPublicationService $service): JsonResponse
    {
        return response()->json($service->publish($request->user(), $curriculum));
    }

    public function archiveCurriculum(ContentOperationRequest $request, int $curriculum, CurriculumPublicationService $service): JsonResponse
    {
        return response()->json($service->archive($request->user(), $curriculum));
    }

    private function download(array $content, string $name): Response
    {
        return response(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200,
            ['Content-Type' => 'application/json; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function exportVersion(ContentOperationRequest $request, int $version, SessionExportService $service): Response
    {
        return $this->download($service->version($request->user(), $version), 'session-version-'.$version.'.json');
    }

    public function exportBatch(ContentOperationRequest $request, string $batch, SessionExportService $service): Response
    {
        return $this->download($service->batch($request->user(), $batch), 'session-package.json');
    }

    public function schema(ContentOperationRequest $request): Response
    {
        return response()->download(config('daily_practice.schema_path'), 'session-package-v1.schema.json', ['Cache-Control' => 'private, no-store']);
    }

    public function template(ContentOperationRequest $request, string $template): Response
    {
        $path = match ($template) {
            'pre-a1-u01' => resource_path('learning/templates/pre-a1-u01-examples.json'),
            'task-types' => resource_path('learning/templates/task-types.json'),
            'pre-a1-u01-v2' => resource_path('learning/programme/packages/PRE_A1/PRE_A1-U01.v2.json'),
            default => null,
        };
        abort_if($path === null, 404);

        return response()->download($path, $template.'.json', ['Cache-Control' => 'private, no-store']);
    }

    public function exportCatalog(ContentOperationRequest $request, int $revision, SessionExportService $service): Response
    {
        $snapshot = \App\Models\Learning\LearningCatalogRevision::query()->findOrFail($revision);

        return $this->download($service->envelope([$snapshot->unit_id === null ? 'levels' : 'units' => [$snapshot->content]]), 'catalog-'.$revision.'.json');
    }

    public function exportCurriculum(ContentOperationRequest $request, int $curriculum, SessionExportService $service): Response
    {
        $snapshot = \App\Models\Learning\LearningCurriculum::query()->findOrFail($curriculum);

        return $this->download($service->envelope(['curricula' => [$service->curriculum($snapshot)]]), 'curriculum-'.$curriculum.'.json');
    }
}
