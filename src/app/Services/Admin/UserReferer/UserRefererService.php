<?php

namespace App\Services\Admin\UserReferer;

use App\Models\UserReferer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class UserRefererService
{
    const PAGINATE = 100;
    const SOURCE_GOOGLE = 'google';
    const SOURCE_YANDEX = 'yandex';
    const SOURCE_OTHER = 'other';

    public function getUserReferersPagination(int $paginate): LengthAwarePaginator
    {
        $userReferers = UserReferer::query()
            ->with(['user'])
            ->orderByDesc('created_at')
            ->paginate($paginate);

        return $userReferers;
    }

    public function getTodaySourceStats(): array
    {
        $stats = [
            self::SOURCE_GOOGLE => 0,
            self::SOURCE_YANDEX => 0,
            self::SOURCE_OTHER => 0,
        ];

        UserReferer::query()
            ->whereBetween('created_at', [Carbon::today(), Carbon::tomorrow()])
            ->pluck('referer_url')
            ->each(function (?string $refererUrl) use (&$stats) {
                $stats[self::detectRefererSource($refererUrl)]++;
            });

        return $stats;
    }

    public static function detectRefererSource(?string $url): string
    {
        if (!$url) {
            return self::SOURCE_OTHER;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            $host = parse_url('https://' . ltrim($url, '/'), PHP_URL_HOST);
        }

        $host = strtolower((string) $host);
        $host = trim(preg_replace('/^www\./', '', $host), '.');

        if (preg_match('/(^|\.)google\.[a-z]{2,}(\.[a-z]{2,})?$/', $host)) {
            return self::SOURCE_GOOGLE;
        }

        if ($host === 'ya.ru' || str_ends_with($host, '.ya.ru') || preg_match('/(^|\.)yandex\.[a-z]{2,}(\.[a-z]{2,})?$/', $host)) {
            return self::SOURCE_YANDEX;
        }

        return self::SOURCE_OTHER;
    }
}
