<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\AccountRunRequest;
use App\Models\Learning\LearningMedia;
use App\Services\Learning\AccountRunService;
use App\Services\Learning\AccountRunView;
use App\Services\Learning\AnswerCheckingException;
use App\Services\Learning\MediaLibraryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyPracticeController extends Controller
{
    public function page(Request $request, ?string $run = null)
    {
        $selectedCompleted = false;
        $selected = config('daily_practice.enabled') && is_string($request->query('session'))
            ? app(\App\Services\Learning\PublicPracticeCatalog::class)->sessions()->where('external_id', $request->query('session'))->with('activeVersion')->first() : null;

        if ($selected && $request->user()) {
            $access = app(\App\Services\Learning\LearningAccessService::class)->forUser($request->user())['sessions'][$selected->external_id] ?? null;
            $selectedCompleted = $access['completed'] ?? false;
            if (! $access || $access['locked']) {
                return redirect('/practice')->with('practice_access_notice', $access['reason'] ?? 'Занятие пока недоступно.');
            }
        }

        return Inertia::render('Daily/Index', ['selectedSession' => $selected ? ['id' => $selected->external_id, 'title' => $selected->activeVersion->payload['title'], 'completed' => $selectedCompleted] : null, 'authUser' => Helper::getUserData(), 'runId' => $request->routeIs('daily.guest') ? null : $run, 'guestRunId' => $request->routeIs('daily.guest') ? $run : null,
            'screen' => $request->routeIs('daily.progress') ? 'progress' : 'today', 'enabled' => (bool) config('daily_practice.enabled'), 'levels' => config('daily_practice.levels')])
            ->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function today(AccountRunRequest $request, AccountRunService $service)
    {
        return response()->json($service->today($request->user()));
    }

    public function profile(AccountRunRequest $request, AccountRunService $service)
    {
        return response()->json($service->profile($request->user()));
    }

    public function history(AccountRunRequest $request, AccountRunService $service)
    {
        return response()->json($service->history($request->user()));
    }

    public function show(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return response()->json($service->load($request->user(), $run));
    }

    private function mutate(AccountRunRequest $request, AccountRunService $service, \Closure $action, ?string $run = null)
    {
        try {
            return response()->json($action());
        } catch (AnswerCheckingException $error) {
            if ($error->errorCode->httpStatus() === 422) {
                return response()->json(['code' => $error->errorCode->value, 'message' => $error->getMessage(), 'errors' => ['request' => [$error->getMessage()]]], 422);
            }
            if ($error->errorCode->httpStatus() !== 409) {
                throw $error;
            }
            $state = $run ? $service->load($request->user(), $run) : $service->today($request->user());

            return response()->json(['code' => $error->errorCode->value, 'message' => $error->getMessage(),
                'revision' => $state['revision'] ?? $state['profile']['revision'], 'state' => $state], 409);
        }
    }

    public function start(AccountRunRequest $request, AccountRunService $service)
    {
        return $this->mutate($request, $service, fn () => $service->start($request->user(), $request->validated()));
    }

    public function profileUpdate(AccountRunRequest $request, AccountRunService $service)
    {
        return $this->mutate($request, $service, fn () => $service->profileUpdate($request->user(), $request->validated()));
    }

    public function answer(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->mutate($request, $service, fn () => $service->event($request->user(), $run, 'answer', $request->validated()), $run);
    }

    public function help(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->mutate($request, $service, fn () => $service->event($request->user(), $run, 'help', $request->validated()), $run);
    }

    public function pause(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->command($request, $run, $service);
    }

    public function resume(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->command($request, $run, $service);
    }

    public function advance(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->command($request, $run, $service);
    }

    public function introduction(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->command($request, $run, $service);
    }

    public function finish(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->command($request, $run, $service);
    }

    private function command(AccountRunRequest $request, string $run, AccountRunService $service)
    {
        return $this->mutate($request, $service, fn () => $service->command($request->user(), $run, $request->route()->getActionMethod(), $request->validated()), $run);
    }

    public function media(AccountRunRequest $request, string $run, string $media, AccountRunService $service, AccountRunView $view, MediaLibraryService $library)
    {
        $snapshot = $service->owned($request->user(), $run)->snapshot;
        abort_unless(in_array($media, $view->assets($snapshot), true), 404);
        $asset = LearningMedia::query()->findOrFail($media);

        return response()->file($library->file($asset), ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
