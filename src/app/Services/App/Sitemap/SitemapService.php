<?php

namespace App\Services\App\Sitemap;

use App\Models\Player\PlayerSongs;
use App\Models\Post\Post;
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

        $sitemap->add(
            Url::create(config('app.url'))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

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

        PlayerSongs::query()
            ->select(['id', 'updated_at'])
            ->where('hidden', false)
            ->orderBy('id')
            ->chunk(500, function ($songs) use ($sitemap) {
                foreach ($songs as $song) {
                    $sitemap->add(
                        $this->makeUrlTag(
                            route('song.show', ['id' => $song->id]),
                            $song->updated_at,
                            Url::CHANGE_FREQUENCY_WEEKLY,
                            0.7
                        )
                    );
                }
            });

        return $sitemap;
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
