@php
    $artistName = data_get($artist, 'name');
    $artistDescription = data_get($artist, 'description');
    $artistImage = data_get($artist, 'image_url');
    $songs = data_get($artist, 'songs', []);
    $lineups = data_get($artist, 'lineups', []);
@endphp

<noscript>
    <main style="max-width:960px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;line-height:1.5;color:#111827">
        <nav style="margin-bottom:20px;font-size:14px">
            <a href="/">Главная</a>
            <span> / </span>
            <a href="{{ route('lyrics') }}">Тексты песен</a>
            <span> / </span>
            <span>{{ $artistName }}</span>
        </nav>

        <header style="margin-bottom:28px">
            @if ($artistImage)
                <img
                    src="{{ $artistImage }}"
                    alt="{{ $artistName }}"
                    width="156"
                    height="156"
                    style="width:156px;height:156px;border-radius:50%;object-fit:cover"
                >
            @endif
            <h1 style="margin:16px 0 8px;font-size:30px">{{ $artistName }} — песни, тексты и переводы</h1>
            @if ($artistDescription)
                <p style="white-space:pre-line">{{ $artistDescription }}</p>
            @endif
        </header>

        <section>
            <h2>Песни исполнителя</h2>
            <ul>
                @foreach ($songs as $song)
                    <li style="margin:6px 0">
                        <a href="{{ route('lyrics.show', ['id' => data_get($song, 'id')]) }}">
                            {{ data_get($song, 'artist_name') }} — {{ data_get($song, 'title') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @if (count($lineups) > 0)
            <section style="margin-top:28px">
                <h2>Совместные исполнения</h2>
                @foreach ($lineups as $lineup)
                    <article style="margin-top:18px">
                        <h3>{{ data_get($lineup, 'name') }}</h3>
                        @if (data_get($lineup, 'description'))
                            <p style="white-space:pre-line">{{ data_get($lineup, 'description') }}</p>
                        @endif
                        <ul>
                            @foreach (data_get($lineup, 'songs', []) as $song)
                                <li>
                                    <a href="{{ route('lyrics.show', ['id' => data_get($song, 'id')]) }}">
                                        {{ data_get($song, 'title') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </section>
        @endif
    </main>
</noscript>
