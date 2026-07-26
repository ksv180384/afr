<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class PrerenderIfCrawler
{
    private const CRAWLER_USER_AGENTS = [
        'Googlebot',
        'YandexBot',
        'bingbot',
        'Baiduspider',
        'Mail.RU_Bot',
        'Slurp',
        'DuckDuckBot',
        'facebookexternalhit',
        'LinkedInBot',
        'Twitterbot',
        'WhatsApp',
        'TelegramBot',
        'Applebot',
    ];

    private const IGNORED_EXTENSIONS = [
        '.js', '.css', '.xml', '.less', '.png', '.jpg', '.jpeg', '.gif',
        '.pdf', '.doc', '.txt', '.ico', '.rss', '.zip', '.mp3', '.rar',
        '.exe', '.wmv', '.avi', '.ppt', '.mpg', '.mpeg', '.tif', '.wav',
        '.mov', '.psd', '.ai', '.xls', '.mp4', '.m4a', '.swf', '.dat',
        '.dmg', '.iso', '.flv', '.m4v', '.torrent', '.ttf', '.woff',
        '.woff2', '.svg', '.eot',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldPrerender($request)) {
            return $next($request);
        }

        $prerenderToken = config('services.prerender.token');
        if (empty($prerenderToken)) {
            return $next($request);
        }

        $prerenderUrl = config('services.prerender.url', 'https://service.prerender.io/');

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Prerender-Token' => $prerenderToken])
                ->get($prerenderUrl . $request->fullUrl());

            if ($response->successful()) {
                return response($response->body(), $response->status())
                    ->header('Content-Type', 'text/html')
                    ->header('X-Prerender', '1');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }

    private function shouldPrerender(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        $uri = $request->getRequestUri();
        foreach (self::IGNORED_EXTENSIONS as $ext) {
            if (str_ends_with(strtolower($uri), $ext)) {
                return false;
            }
        }

        $userAgent = $request->userAgent() ?? '';
        foreach (self::CRAWLER_USER_AGENTS as $crawler) {
            if (stripos($userAgent, $crawler) !== false) {
                return true;
            }
        }

        return false;
    }
}
