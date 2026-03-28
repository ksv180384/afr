<?php

namespace App\Console\Commands;

use App\Services\App\Sitemap\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Сгенерировать sitemap.xml (главная, опубликованные посты, видимые песни)';

    public function handle(SitemapService $sitemapService): int
    {
        $this->info('Генерация sitemap.xml…');
        $sitemapService->generate();
        $this->info('Готово: '.public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
