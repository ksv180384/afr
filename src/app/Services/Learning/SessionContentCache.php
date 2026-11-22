<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningSession;
use Illuminate\Support\Facades\Cache;

final class SessionContentCache
{
    public function payload(LearningSession $session): array
    {
        $key = 'daily-practice:lesson:'.$session->id.':'.$session->checksum;
        $load = fn (): array => $session->payload ?? LearningSession::where('checksum', $session->checksum)
            ->findOrFail($session->id, ['payload'])->payload;
        try {
            return Cache::remember($key, 3600, $load);
        } catch (\Throwable) {
            app(PracticeTelemetry::class)->record('content_cache_unavailable', ['status' => 503]);

            return $load();
        }
    }
}
