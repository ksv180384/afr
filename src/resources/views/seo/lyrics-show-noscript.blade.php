@php
    $songTitle = data_get($song, 'title');
    $songArtist = data_get($song, 'artist_name');
    $textFr = data_get($song, 'text_fr', []);
    $textRu = data_get($song, 'text_ru', []);
    $textTranscription = data_get($song, 'text_transcription', []);
    $rowsCount = max(count($textFr), count($textRu), count($textTranscription));
@endphp

<noscript>
    <div style="max-width:1100px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;line-height:1.5;color:#111827">
        <nav style="margin-bottom:20px;font-size:14px">
            <a href="/" style="color:#0369a1">Главная</a>
            <span> / </span>
            <a href="/lyrics" style="color:#0369a1">Тексты песен</a>
        </nav>

        <h1 style="margin:0 0 12px;font-size:28px;line-height:1.2">
            {{ $songArtist }} - {{ $songTitle }}: текст, перевод и транскрипция
        </h1>
        <p style="margin:0 0 24px">
            Французский текст песни {{ $songArtist }} - {{ $songTitle }} с переводом на русский и транскрипцией.
        </p>

        @if ($rowsCount > 0)
            <table style="width:100%;border-collapse:collapse;font-size:15px">
                <thead>
                    <tr>
                        <th style="text-align:left;border-bottom:1px solid #bae6fd;padding:8px">Французский текст</th>
                        <th style="text-align:left;border-bottom:1px solid #bae6fd;padding:8px">Перевод</th>
                        <th style="text-align:left;border-bottom:1px solid #bae6fd;padding:8px">Транскрипция</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($index = 0; $index < $rowsCount; $index++)
                        <tr>
                            <td style="vertical-align:top;border-bottom:1px solid #e0f2fe;padding:8px">{{ $textFr[$index] ?? '' }}</td>
                            <td style="vertical-align:top;border-bottom:1px solid #e0f2fe;padding:8px">{{ $textRu[$index] ?? '' }}</td>
                            <td style="vertical-align:top;border-bottom:1px solid #e0f2fe;padding:8px">{{ $textTranscription[$index] ?? '' }}</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        @endif
    </div>
</noscript>
