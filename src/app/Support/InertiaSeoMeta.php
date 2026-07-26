<?php

namespace App\Support;

use Illuminate\Http\Request;

class InertiaSeoMeta
{
    public static function make(array $page, ?Request $request = null): array
    {
        $request ??= request();

        $appName = config('app.name', 'ApprendreFr');
        $component = $page['component'] ?? null;
        $canonicalUrl = url()->current();

        $meta = [
            'title' => $appName,
            'description' => 'Изучайте французский язык: грамматика, уроки, тексты песен с переводом и транскрипцией, словарь.',
            'canonicalUrl' => $canonicalUrl,
            'noIndex' => false,
            'jsonLd' => null,
            'pageType' => $component,
        ];

        return match ($component) {
            'Grammar/Grammar' => self::grammarIndex($meta, $appName),
            'Grammar/GrammarShow' => self::grammarShow($meta, $appName, data_get($page, 'props.grammarContent'), $canonicalUrl),
            'Lessons/Lessons' => self::lessonsIndex($meta, $appName),
            'Lessons/LessonShow' => self::lessonShow($meta, $appName, data_get($page, 'props.lessonContent'), $canonicalUrl),
            'Dictionary/Dictionary' => self::dictionaryIndex($meta, $appName, $request),
            'Dictionary/DictionaryShow' => self::dictionaryShow($meta, $appName, data_get($page, 'props.word')),
            'Lyrics/Lyrics' => self::lyricsIndex($meta, $appName),
            'Lyrics/LyricShow' => self::lyricShow($meta, $appName, data_get($page, 'props.song'), $canonicalUrl),
            'Post/PostShow' => self::postShow($meta, $appName, data_get($page, 'props.post'), $canonicalUrl),
            default => $meta,
        };
    }

    private static function grammarIndex(array $meta, string $appName): array
    {
        return [
            ...$meta,
            'title' => 'Грамматика французского языка | ' . $appName,
            'description' => 'Грамматика французского языка: правила, объяснения и примеры для изучающих французский.',
        ];
    }

    private static function grammarShow(array $meta, string $appName, mixed $content, string $canonicalUrl): array
    {
        $title = data_get($content, 'title');

        if (!$title) {
            return $meta;
        }

        $description = data_get($content, 'description') ?: 'Грамматика французского языка: ' . $title . '. Правила, объяснения и примеры.';
        $articleEntityId = $canonicalUrl . '#article';
        $breadcrumbsEntityId = $canonicalUrl . '#breadcrumbs';

        return [
            ...$meta,
            'title' => $title . ' - грамматика французского языка | ' . $appName,
            'description' => $description,
            'jsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    '@id' => $articleEntityId,
                    'headline' => $title,
                    'description' => $description,
                    'inLanguage' => 'ru',
                    'mainEntityOfPage' => $canonicalUrl,
                    'url' => $canonicalUrl,
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => $appName,
                        'url' => url('/'),
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    '@id' => $breadcrumbsEntityId,
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Главная',
                            'item' => url('/'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Грамматика',
                            'item' => route('grammar'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $title,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function lessonsIndex(array $meta, string $appName): array
    {
        return [
            ...$meta,
            'title' => 'Уроки французского языка | ' . $appName,
            'description' => 'Уроки французского языка для начинающих: темы для общения, слова, упражнения и произношение.',
        ];
    }

    private static function lessonShow(array $meta, string $appName, mixed $content, string $canonicalUrl): array
    {
        $title = data_get($content, 'title');

        if (!$title) {
            return $meta;
        }

        $description = data_get($content, 'description') ?: 'Урок французского языка: ' . $title . '. Слова, примеры и упражнения для начинающих.';
        $learningEntityId = $canonicalUrl . '#learning-resource';
        $breadcrumbsEntityId = $canonicalUrl . '#breadcrumbs';

        return [
            ...$meta,
            'title' => $title . ' - урок французского языка | ' . $appName,
            'description' => $description,
            'jsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'LearningResource',
                    '@id' => $learningEntityId,
                    'name' => $title,
                    'description' => $description,
                    'educationalLevel' => 'beginner',
                    'learningResourceType' => 'lesson',
                    'inLanguage' => 'ru',
                    'mainEntityOfPage' => $canonicalUrl,
                    'url' => $canonicalUrl,
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => $appName,
                        'url' => url('/'),
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    '@id' => $breadcrumbsEntityId,
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Главная',
                            'item' => url('/'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Уроки',
                            'item' => route('lessons'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $title,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function dictionaryIndex(array $meta, string $appName, Request $request): array
    {
        $dictionaryTitle = $request->query('lang') === 'ru'
            ? 'Русско-французский словарь'
            : 'Французско-русский словарь';

        return [
            ...$meta,
            'title' => $dictionaryTitle . ' | ' . $appName,
            'description' => $dictionaryTitle . ': перевод, транскрипция, произношение и примеры употребления слов.',
            'noIndex' => $request->hasAny(['parts_of_speech', 'page', 'lang']),
        ];
    }

    private static function dictionaryShow(array $meta, string $appName, mixed $word): array
    {
        $wordText = data_get($word, 'word');
        $translation = data_get($word, 'translation');

        if (!$wordText || !$translation) {
            return $meta;
        }

        $description = $wordText . ' - ' . $translation;
        $transcription = data_get($word, 'transcription');

        if ($transcription) {
            $description .= ' (' . $transcription . ')';
        }

        return [
            ...$meta,
            'title' => $wordText . ' - ' . $translation . ' | ' . $appName,
            'description' => $description . ': перевод, транскрипция, произношение и примеры употребления.',
        ];
    }

    private static function lyricsIndex(array $meta, string $appName): array
    {
        return [
            ...$meta,
            'title' => 'Тексты французских песен с переводом и транскрипцией | ' . $appName,
            'description' => 'Тексты французских песен с построчным переводом на русский язык, транскрипцией и караоке для изучения произношения.',
        ];
    }

    private static function lyricShow(array $meta, string $appName, mixed $song, string $canonicalUrl): array
    {
        $songTitle = data_get($song, 'title');
        $songArtist = data_get($song, 'artist_name');

        if (!$songTitle || !$songArtist) {
            return $meta;
        }

        $pageTitle = $songArtist . ' - ' . $songTitle . ': текст, перевод и транскрипция | ' . $appName;
        $description = 'Французский текст песни ' . $songArtist . ' - ' . $songTitle
            . ' с переводом на русский и транскрипцией. Слушайте караоке и изучайте произношение.';
        $musicEntityId = $canonicalUrl . '#music-composition';
        $learningEntityId = $canonicalUrl . '#learning-resource';
        $breadcrumbsEntityId = $canonicalUrl . '#breadcrumbs';

        return [
            ...$meta,
            'title' => $pageTitle,
            'description' => $description,
            'jsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'MusicComposition',
                    '@id' => $musicEntityId,
                    'name' => $songTitle,
                    'composer' => [
                        '@type' => 'Person',
                        'name' => $songArtist,
                    ],
                    'inLanguage' => 'fr',
                    'url' => $canonicalUrl,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'LearningResource',
                    '@id' => $learningEntityId,
                    'name' => $songArtist . ' - ' . $songTitle . ': текст, перевод и транскрипция',
                    'description' => $description,
                    'learningResourceType' => 'lyrics translation',
                    'inLanguage' => ['fr', 'ru'],
                    'url' => $canonicalUrl,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    '@id' => $breadcrumbsEntityId,
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Главная',
                            'item' => url('/'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Тексты песен',
                            'item' => route('lyrics'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $songArtist . ' - ' . $songTitle,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function postShow(array $meta, string $appName, mixed $post, string $canonicalUrl): array
    {
        $title = data_get($post, 'title');
        $author = data_get($post, 'user.name');

        if (!$title) {
            return $meta;
        }

        $description = self::makePostDescription($post, $title, $author);
        $articleEntityId = $canonicalUrl . '#article';
        $breadcrumbsEntityId = $canonicalUrl . '#breadcrumbs';

        return [
            ...$meta,
            'title' => $title . ' | ' . $appName,
            'description' => $description,
            'jsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    '@id' => $articleEntityId,
                    'headline' => $title,
                    'description' => $description,
                    'author' => [
                        '@type' => 'Person',
                        'name' => $author ?: $appName,
                    ],
                    'datePublished' => data_get($post, 'created_at_iso') ?: data_get($post, 'created_at'),
                    'dateModified' => data_get($post, 'updated_at_iso') ?: data_get($post, 'updated_at'),
                    'inLanguage' => 'ru',
                    'mainEntityOfPage' => $canonicalUrl,
                    'url' => $canonicalUrl,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    '@id' => $breadcrumbsEntityId,
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Главная',
                            'item' => url('/'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => $title,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function makePostDescription(mixed $post, string $title, ?string $author): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) data_get($post, 'content', ''))));

        if ($text !== '') {
            return mb_strlen($text) > 180 ? mb_substr($text, 0, 177) . '...' : $text;
        }

        $authorText = $author ? ' автора ' . $author : '';

        return $title . ' - статья' . $authorText . ' на ApprendreFr о французском языке.';
    }
}
