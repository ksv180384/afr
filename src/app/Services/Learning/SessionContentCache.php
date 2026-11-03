<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningSessionVersion;
use Illuminate\Support\Facades\Cache;

final class SessionContentCache
{
    public function payload(LearningSessionVersion $version): array
    {
        $key = 'daily-practice:content:'.$version->id.':'.$version->version.':'.$version->checksum;
        try {
            return Cache::remember($key, 3600, fn (): array => $version->payload ?? LearningSessionVersion::findOrFail($version->id)->payload);
        } catch (\Throwable) {
            app(PracticeTelemetry::class)->record('content_cache_unavailable', ['status' => 503]);

            return $version->payload ?? LearningSessionVersion::findOrFail($version->id)->payload;
        }
    }
}
