<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @php($catalog = $page['props']['catalog'])
  @php($seo = $catalog['seo'])
  <title inertia>{{ $seo['title'] }} | ApprendreFr</title>
  <meta inertia="description" name="description" content="{{ $seo['description'] }}">
  <meta inertia="robots" name="robots" content="{{ $seo['noIndex'] ? 'noindex, follow' : 'index, follow' }}">
  <link inertia="canonical" rel="canonical" href="{{ $seo['canonicalUrl'] }}">
  <meta inertia="og:url" property="og:url" content="{{ $seo['canonicalUrl'] }}">
  <meta inertia="og:title" property="og:title" content="{{ $seo['title'] }}">
  <meta inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
  <meta inertia="twitter:title" name="twitter:title" content="{{ $seo['title'] }}">
  <meta inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
  <script inertia="jsonld" type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
  @routes
  @vite(['resources/js/app.js', 'resources/js/App/Pages/Practice/Index.vue'])
</head>
<body class="font-sans antialiased">
  <div id="app" data-page="{{ json_encode($page) }}">
    <div class="practice-static">
    <header class="practice-server-header"><a href="/">Главная</a><a href="/practice">Практика</a><a href="/grammar">Грамматика</a><a href="/lessons">Уроки</a><a href="/dictionary">Словарь</a><a href="/games">Игры</a></header>
    <main class="practice-shell">
      @include('practice.content', ['catalog' => $catalog])
    </main></div>
  </div>
</body>
</html>
