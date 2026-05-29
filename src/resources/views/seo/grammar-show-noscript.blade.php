@php
    $grammarTitle = data_get($grammarContent, 'title');
    $grammarDescription = data_get($grammarContent, 'description');
    $grammarBody = data_get($grammarContent, 'content');
@endphp

<noscript>
    <div style="max-width:1000px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;line-height:1.6;color:#111827">
        <nav style="margin-bottom:20px;font-size:14px">
            <a href="/" style="color:#0369a1">Главная</a>
            <span> / </span>
            <a href="/grammar" style="color:#0369a1">Грамматика</a>
        </nav>

        <article>
            <header style="margin-bottom:24px">
                <h1 style="margin:0 0 12px;font-size:30px;line-height:1.2">
                    {{ $grammarTitle }}
                </h1>

                @if ($grammarDescription)
                    <p style="margin:0;color:#4b5563">
                        {{ $grammarDescription }}
                    </p>
                @endif
            </header>

            <div>
                {!! $grammarBody !!}
            </div>
        </article>
    </div>
</noscript>
