<meta name="afr-cookie-preferences" content="{{ match (\App\Support\CookiePreferences::choice(request())) { true => 'analytics', false => 'essential', default => '' } }}">
@production
    <meta name="afr-metrika-counter" content="13697989">
@endproduction
