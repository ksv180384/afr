@php
    $postTitle = data_get($post, 'title');
    $postContent = data_get($post, 'content');
    $postAuthor = data_get($post, 'user.name');
    $postCreatedAt = data_get($post, 'created_at');
@endphp

<noscript>
    <div style="max-width:900px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;line-height:1.6;color:#111827">
        <nav style="margin-bottom:20px;font-size:14px">
            <a href="/" style="color:#0369a1">Главная</a>
        </nav>

        <article>
            <header style="margin-bottom:24px">
                @if ($postAuthor || $postCreatedAt)
                    <p style="margin:0 0 8px;color:#4b5563;font-size:14px">
                        @if ($postAuthor)
                            {{ $postAuthor }}
                        @endif
                        @if ($postAuthor && $postCreatedAt)
                            <span> / </span>
                        @endif
                        @if ($postCreatedAt)
                            <time>{{ $postCreatedAt }}</time>
                        @endif
                    </p>
                @endif

                <h1 style="margin:0;font-size:30px;line-height:1.2">
                    {{ $postTitle }}
                </h1>
            </header>

            <div>
                {!! $postContent !!}
            </div>
        </article>
    </div>
</noscript>
