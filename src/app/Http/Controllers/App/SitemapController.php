<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\App\Sitemap\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemapService): Response
    {
        return response($sitemapService->renderXml(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
