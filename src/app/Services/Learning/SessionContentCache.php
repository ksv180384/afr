<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningSession;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Support\Facades\Cache;

final class SessionContentCache
{
    public function pruneExpired(): int
    {
        $cache = Cache::store();
        $store = $cache->getStore();
        if (! $store instanceof DatabaseStore || ! $cache->add('daily-practice:cache-sweep', 1, 60)) {
            return 0;
        }

        $table = config('cache.stores.'.Cache::getDefaultDriver().'.table', 'cache');
        $query = $store->getConnection()->table($table);
        $key = $query->getGrammar()->wrap('key');
        $prefix = strtr($store->getPrefix().'daily-practice:', ['!' => '!!', '_' => '!_', '%' => '!%']);
        $expired = $query->whereRaw("$key LIKE ? ESCAPE '!'", [$prefix.'%'])->where('expiration', '<=', now()->timestamp);
        $keys = (clone $expired)->orderBy('key')->limit(100)->pluck('key');

        // Recheck expiration in case another request refreshed a key after the scan.
        return $keys->isEmpty() ? 0 : $expired->whereIn('key', $keys)->delete();
    }

    public function payload(LearningSession $session): array
    {
        $key = 'daily-practice:lesson:'.$session->id.':'.$session->checksum;
        $load = fn (): array => $session->payload ?? LearningSession::where('checksum', $session->checksum)
            ->findOrFail($session->id, ['id', 'payload'])->payload;
        try {
            return Cache::remember($key, 3600, $load);
        } catch (\Throwable) {
            app(PracticeTelemetry::class)->record('content_cache_unavailable', ['status' => 503]);

            return $load();
        }
    }
}
