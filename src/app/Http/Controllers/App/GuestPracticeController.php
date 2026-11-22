<?php

namespace App\Http\Controllers\App;

use App\Enums\Learning\LearningLevel;
use App\Enums\Learning\PracticeErrorCode;
use App\Http\Controllers\Controller;
use App\Models\Learning\LearningMedia;
use App\Models\Learning\LearningSession;
use App\Services\Learning\AccountRunView;
use App\Services\Learning\AnswerCheckingException;
use App\Services\Learning\AvailableLevels;
use App\Services\Learning\CheckingPolicy;
use App\Services\Learning\LearningAccessService;
use App\Services\Learning\MediaLibraryService;
use App\Services\Learning\TrainingSelection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestPracticeController extends Controller
{
    private function published()
    {
        return LearningSession::query()->select('learning_sessions.*')
            ->join('learning_units', 'learning_units.id', '=', 'learning_sessions.unit_id')
            ->whereNull('learning_sessions.archived_at')
            ->whereHas('activeVersion', fn ($query) => $query->where('status', 'published'))
            ->whereHas('unit', fn ($query) => $query->whereNotNull('published_at')->whereNull('archived_at'))
            ->whereHas('levelDefinition', fn ($query) => $query->published())
            ->with(['activeVersion' => fn ($query) => $query->select('id', 'session_id', 'version', 'status', 'checksum',
                'content_title', 'content_duration', 'content_goal', 'content_prerequisites'), 'unit'])->orderBy('learning_units.sort_order')
            ->orderBy('learning_sessions.sort_order')->orderBy('learning_sessions.external_id');
    }

    public function training(Request $request, TrainingSelection $training, LearningAccessService $access)
    {
        $input = $request->validate([
            'mode' => 'required|in:recall,dialogue',
            'unit' => 'sometimes|required|string|max:128',
            'current_level' => ['sometimes', Rule::enum(LearningLevel::class)],
            'completed' => 'sometimes|array|max:2000', 'completed.*' => 'string|max:160',
            'current' => 'nullable|string|max:160', 'seen' => 'sometimes|array|max:1000', 'seen.*' => 'string|max:160',
            'previous' => isset($request->unit) ? 'sometimes|array|max:100' : 'sometimes|array|max:6',
            'previous.*' => 'string|max:400',
        ]);
        $previous = $input['previous'] ?? [];
        if (isset($input['unit'])) {
            $states = $request->user()
                ? $access->forUser($request->user())
                : $access->states($access->outline(), $input['completed'] ?? [], $input['current_level'] ?? AvailableLevels::first());
            $topic = $states['units'][$input['unit']] ?? null;
            if ($topic === null) {
                return response()->json(['items' => []]);
            }
            if ($topic['locked']) {
                throw new AnswerCheckingException(PracticeErrorCode::Forbidden, $topic['reason']);
            }
            return response()->json(['items' => $training->selectTopic($this->published(), $input['mode'], $input['unit'], $previous)]);
        }
        [$completed, $current, $seen] = $request->user()
            ? $training->accountProgress($request->user())
            : [$input['completed'] ?? [], $input['current'] ?? null, $input['seen'] ?? []];
        return response()->json(['items' => $training->select($this->published(), $input['mode'], $completed,
            $current, $seen, $previous)]);
    }

    public function index()
    {
        return response()->json(['access_outline' => app(\App\Services\Learning\LearningAccessService::class)->outline(), 'sessions' => $this->published()->get()->map(fn ($session) => [
            'id' => $session->external_id, 'theme' => $session->unit->title, 'duration_minutes' => (int) $session->activeVersion->content_duration, 'title' => $session->activeVersion->content_title,
            'level' => $session->getRawOriginal('level'), 'goal' => $session->activeVersion->content_goal, 'session_version_id' => $session->active_version_id,
            'prerequisite_ids' => json_decode($session->activeVersion->content_prerequisites, true),
        ])], options: JSON_UNESCAPED_UNICODE);
    }

    public function show(string $session, AccountRunView $view, \App\Services\Learning\SessionContentCache $cache)
    {
        $row = $this->published()->where('learning_sessions.external_id', $session)->firstOrFail();
        $payload = $cache->payload($row->activeVersion);
        $assets = $view->assets($payload);

        // Guest checking is local. Only the active published teaching payload is exposed.
        return response()->json(['access_outline' => app(\App\Services\Learning\LearningAccessService::class)->outline(), 'session_version_id' => $row->active_version_id,
            'checking_policy_version' => CheckingPolicy::CURRENT_VERSION, 'snapshot' => $payload,
            'checksum' => $row->activeVersion->checksum,
            'audioUrls' => array_combine($assets, array_map(fn ($id) => route('daily.content.media', ['session' => $session, 'media' => $id], false), $assets)) ?: []]);
    }

    public function media(string $session, string $media, AccountRunView $view, MediaLibraryService $library, \App\Services\Learning\SessionContentCache $cache)
    {
        $row = $this->published()->where('learning_sessions.external_id', $session)->firstOrFail();
        abort_unless(in_array($media, $view->assets($cache->payload($row->activeVersion)), true), 404);
        $asset = LearningMedia::query()->where('review_status', 'approved')->whereNull('archived_at')->findOrFail($media);

        return response()->file($library->file($asset), ['Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
