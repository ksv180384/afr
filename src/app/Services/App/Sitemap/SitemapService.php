<?php

namespace App\Services\App\Sitemap;

use App\Models\Lesson;
use App\Models\Player\PlayerSongs;
use App\Models\Post\Post;
use App\Models\Word\Word;
use DateTimeInterface;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapService
{
    public function renderXml(): string
    {
        return $this->build()->render();
    }

    public function generate(?string $path = null): void
    {
        $path ??= public_path('sitemap.xml');
        $this->build()->writeToFile($path);
    }

    private function build(): Sitemap
    {
        $sitemap = Sitemap::create();

        $this->addStaticPages($sitemap);
        $this->addPosts($sitemap);
        $this->addLyrics($sitemap);
//        $this->addLessons($sitemap);
//        $this->addDictionaryWords($sitemap);

        return $sitemap;
    }

    private function addStaticPages(Sitemap $sitemap): void
    {
        $sitemap->add(
            Url::create(config('app.url'))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

        $staticPages = [
            ['route' => 'grammar', 'freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            ['route' => 'lessons', 'freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            ['route' => 'lyrics', 'freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            ['route' => 'dictionary', 'freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
        ];

        foreach ($staticPages as $page) {
            $sitemap->add(
                Url::create(route($page['route']))
                    ->setChangeFrequency($page['freq'])
                    ->setPriority($page['priority'])
            );
        }
    }

    private function addPosts(Sitemap $sitemap): void
    {
        Post::query()
            ->select(['id', 'updated_at'])
            ->whereHas('status', function ($q) {
                $q->where('alias', 'published');
            })
            ->orderBy('id')
            ->chunk(500, function ($posts) use ($sitemap) {
                foreach ($posts as $post) {
                    $sitemap->add(
                        $this->makeUrlTag(
                            route('post.show', ['id' => $post->id]),
                            $post->updated_at,
                            Url::CHANGE_FREQUENCY_WEEKLY,
                            0.8
                        )
                    );
                }
            });
    }

    private function addLyrics(Sitemap $sitemap): void
    {
        PlayerSongs::query()
            ->select(['id', 'updated_at'])
            ->where('hidden', false)
            ->orderBy('id')
            ->chunk(500, function ($songs) use ($sitemap) {
                foreach ($songs as $song) {
                    $sitemap->add(
                        $this->makeUrlTag(
                            route('lyrics.show', ['id' => $song->id]),
                            $song->updated_at,
                            Url::CHANGE_FREQUENCY_WEEKLY,
                            0.7
                        )
                    );
                }
            });
    }

    private function addLessons(Sitemap $sitemap): void
    {
        Lesson::query()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($lessons) use ($sitemap) {
                foreach ($lessons as $lesson) {
                    $sitemap->add(
                        $this->makeUrlTag(
                            route('lesson.show', ['id' => $lesson->id]),
                            $lesson->updated_at,
                            Url::CHANGE_FREQUENCY_MONTHLY,
                            0.7
                        )
                    );
                }
            });
    }

    private function addDictionaryWords(Sitemap $sitemap): void
    {
        Word::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunk(500, function ($words) use ($sitemap) {
                foreach ($words as $word) {
                    $sitemap->add(
                        $this->makeUrlTag(
                            route('dictionary.show', ['id' => $word->id]),
                            null,
                            Url::CHANGE_FREQUENCY_MONTHLY,
                            0.5
                        )
                    );
                }
            });
    }

    private function makeUrlTag(string $location, mixed $lastModified, string $changeFrequency, float $priority): Url
    {
        $tag = Url::create($location)
            ->setChangeFrequency($changeFrequency)
            ->setPriority($priority);

        if ($lastModified instanceof DateTimeInterface) {
            $tag->setLastModificationDate($lastModified);
        }

        return $tag;
    }
}
