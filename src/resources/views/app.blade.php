<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'ApprendreFr') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="{{ config('app.name', 'ApprendreFr') }}" />
    <meta property="og:locale" content="ru_RU" />
    <meta property="og:image" content="{{ asset('img/og-default.jpg') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />

    <meta name="twitter:card" content="summary_large_image" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/App/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased">
@inertia

<noscript>
    <div style="max-width:800px;margin:0 auto;padding:40px 20px;font-family:sans-serif">
        <h1>ApprendreFr — Французский язык онлайн</h1>
        <p>Изучайте французский язык: грамматика, уроки, тексты песен с переводом и транскрипцией, словарь.</p>
        <nav>
            <ul>
                <li><a href="/">Главная</a></li>
                <li><a href="/grammar">Грамматика французского языка</a></li>
                <li><a href="/lessons">Уроки французского языка</a></li>
                <li><a href="/lyrics">Тексты и переводы французских песен</a></li>
                <li><a href="/dictionary">Французско-русский словарь</a></li>
            </ul>
        </nav>
    </div>
</noscript>

@production
    <!-- Yandex.Metrika counter (deferred to not block rendering) -->
    <script type="text/javascript">
        window.addEventListener('load', function() {
            setTimeout(function() {
                (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                    m[i].l=1*new Date();
                    for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                    k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

                ym(13697989, "init", {
                    clickmap:true,
                    trackLinks:true,
                    accurateTrackBounce:true,
                    webvisor:true
                });
            }, 2000);
        });
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/13697989" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
@endproduction
</body>
</html>
