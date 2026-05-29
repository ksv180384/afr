@php
    $lessonTitle = data_get($lessonContent, 'title');
    $lessonDescription = data_get($lessonContent, 'description');
    $lessonBody = data_get($lessonContent, 'content');
@endphp

<noscript>
    <div style="max-width:1000px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;line-height:1.6;color:#111827">
        <nav style="margin-bottom:20px;font-size:14px">
            <a href="/" style="color:#0369a1">Главная</a>
            <span> / </span>
            <a href="/lessons" style="color:#0369a1">Уроки</a>
        </nav>

        <article>
            <header style="margin-bottom:24px">
                <h1 style="margin:0 0 12px;font-size:30px;line-height:1.2">
                    {{ $lessonTitle }}
                </h1>

                @if ($lessonDescription)
                    <p style="margin:0;color:#4b5563">
                        {{ $lessonDescription }}
                    </p>
                @endif
            </header>

            <div>
                {!! $lessonBody !!}
            </div>
        </article>
    </div>
</noscript>
