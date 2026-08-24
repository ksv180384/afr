<noscript>
    <div style="max-width:900px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;line-height:1.5;color:#111827">
        <h1 style="margin:0 0 20px;font-size:28px;line-height:1.2">
            Тексты, транскрипции и переводы французских песен
        </h1>

        @foreach ($artists as $artist)
            @php($songs = data_get($artist, 'songs', []))

            @if (count($songs) > 0)
                <section style="margin-bottom:20px">
                    <h2 style="margin:0 0 8px;font-size:20px">
                        @if (data_get($artist, 'id'))
                            <a href="{{ route('lyrics.artist.show', ['id' => data_get($artist, 'id')]) }}">
                                {{ data_get($artist, 'name') }}
                            </a>
                        @else
                            {{ data_get($artist, 'name') }}
                        @endif
                    </h2>
                    <ul style="margin:0;padding-left:24px">
                        @foreach ($songs as $song)
                            <li style="margin:4px 0">
                                <a href="{{ route('lyrics.show', ['id' => data_get($song, 'id')]) }}">
                                    {{ data_get($song, 'title') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    </div>
</noscript>
