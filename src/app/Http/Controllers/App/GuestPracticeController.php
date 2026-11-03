<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Learning\LearningMedia;
use App\Models\Learning\LearningSession;
use App\Services\Learning\AccountRunView;
use App\Services\Learning\CheckingPolicy;
use App\Services\Learning\MediaLibraryService;

class GuestPracticeController extends Controller
{
    private function published()
    {
        return LearningSession::query()->select('learning_sessions.*')
            ->join('learning_units', 'learning_units.id', '=', 'learning_sessions.unit_id')
            ->whereNull('learning_sessions.archived_at')
            ->whereHas('activeVersion', fn ($query) => $query->where('status', 'published'))
            ->whereHas('unit', fn ($query) => $query->whereNotNull('published_at')->whereNull('archived_at'))
            ->whereHas('levelDefinition', fn ($query) => $query->whereNotNull('published_at'))
            ->with(['activeVersion' => fn ($query) => $query->select('id', 'session_id', 'version', 'status', 'checksum',
                'payload->title as content_title', 'payload->duration_minutes as content_duration', 'payload->prerequisite_ids as content_prerequisites'), 'unit'])->orderBy('learning_units.sort_order')
            ->orderBy('learning_sessions.sort_order')->orderBy('learning_sessions.external_id');
    }

    public function index()
    {
        return response()->json(['access_outline' => app(\App\Services\Learning\LearningAccessService::class)->outline(), 'sessions' => $this->published()->get()->map(fn ($session) => [
            'id' => $session->external_id, 'theme' => $session->unit->title, 'duration_minutes' => (int) $session->activeVersion->content_duration, 'title' => $session->activeVersion->content_title,
            'level' => $session->getRawOriginal('level'), 'session_version_id' => $session->active_version_id,
            'prerequisite_ids' => json_decode($session->activeVersion->content_prerequisites, true),
        ])]);
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
