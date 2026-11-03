<header class="practice-section-header">
<nav class="practice-nav" aria-label="Навигация практики"><a href="/practice" aria-current="page">Все задания по уровням</a><a href="/daily">Сегодня</a><a href="/daily/vocabulary">Мой словарь</a><a href="/daily/progress">Прогресс</a></nav>
<h1 class="practice-heading">{{ $catalog['title'] }}</h1></header><div class="practice-body">
@if($catalog['kind'] !== 'index')
<nav aria-label="Хлебные крошки" class="practice-breadcrumbs">
@foreach($catalog['breadcrumbs'] as $crumb)
  @if(!$loop->last)<a href="{{ $crumb['path'] }}">{{ $crumb['title'] }}</a><span aria-hidden="true">/</span>
  @else<span aria-current="page">{{ $crumb['title'] }}</span>@endif
@endforeach
</nav>
@endif
@if(!empty($page['props']['accessNotice']))<p role="status">{{ $page['props']['accessNotice'] }}</p>@endif
@if(!empty($catalog['access']['locked']))<p role="status">{{ $catalog['access']['reason'] }}</p>
@else
<p>{{ $catalog['summary'] }}</p>
@if($catalog['kind'] === 'index')<p>Сессия обычно занимает около пяти минут: сначала знакомство с выражениями, затем вопросы и разбор. Занятия открываются по порядку. Завершённые материалы доступны для повторения. Без регистрации прогресс сохраняется в этом браузере.</p>@endif
@if($catalog['outcomes'])<h2>Чему вы научитесь</h2><ul>@foreach($catalog['outcomes'] as $outcome)<li>{{ $outcome }}</li>@endforeach</ul>@endif
@include('practice.notes', ['notes' => $catalog['notes']])
@if(!$catalog['indexable'])<p role="status">Материалы этой страницы ещё готовятся. Новые занятия появятся после публикации.</p>@endif
@if($catalog['session'])
  @php($session = $catalog['session'])
  <p>Уровень: {{ $session['level'] }} · Около {{ $session['duration_minutes'] }} мин</p>
  <h2>Цели занятия</h2><ul>@foreach($session['objectives'] as $goal)<li>{{ $goal['title'] }}</li>@endforeach</ul>
  @include('practice.notes', ['notes' => $session['pronunciation_notes']])
  @foreach($session['public_page']['explanation_blocks'] as $block)
    <section><h2>{{ $block['heading'] }}</h2>
      @foreach($block['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
      @include('practice.notes', ['notes' => $block['pronunciation_notes'] ?? []])
      @foreach($block['examples'] ?? [] as $example)@include('practice.french', ['item' => $example])@endforeach
    </section>
  @endforeach
  <h2>Выражения занятия</h2>
  @foreach($session['vocabulary'] as $word)
    @include('practice.french', ['item' => $word])
    @if(!empty($word['example']))@include('practice.french', ['item' => $word['example']])@endif
  @endforeach
  <p><a class="practice-primary" href="/daily?session={{ rawurlencode($session['id']) }}">Начать практику</a></p>
  <p>Начало проверяет учебные зависимости. Просмотр материала и отдельная практика не меняют выбранный уровень.</p>
@endif
@if($catalog['cards'])
  <h2>{{ $catalog['kind'] === 'index' ? 'Все уровни' : ($catalog['kind'] === 'level' ? 'Темы уровня' : 'Занятия темы') }}</h2>
  <ul class="practice-cards">@foreach($catalog['cards'] as $card)<li>
    <h3>@if($card['path'] && empty($card['locked']))<a href="{{ $card['path'] }}">{{ $card['title'] }}</a>@else{{ $card['title'] }}@endif</h3>
    <p>{{ $card['summary'] }}</p>
    @if(!empty($card['locked']))<p role="status">🔒 {{ $card['reason'] }}</p>@elseif(!empty($card['completed']))<p>Пройдено · доступно для повторения</p>@elseif(!empty($card['review_only']))<p>Доступно по вашему уровню</p>@endif
    @if(isset($card['count']))<p>Опубликовано занятий: {{ $card['count'] }}</p>@endif
    @if(isset($card['duration']))<p>Около {{ $card['duration'] }} мин</p>@endif
  </li>@endforeach</ul>
@endif
@if($catalog['related'])<h2>Связанные и предыдущие материалы</h2><ul>@foreach($catalog['related'] as $card)<li>@if(empty($card['locked']))<a href="{{ $card['path'] }}">{{ $card['title'] }}</a>@else{{ $card['title'] }} · 🔒@endif</li>@endforeach</ul>@endif
<nav aria-label="По теме" class="practice-breadcrumbs">@if($catalog['previous'] && empty($catalog['previous']['locked']))<a href="{{ $catalog['previous']['path'] }}">Предыдущее: {{ $catalog['previous']['title'] }}</a>@endif @if($catalog['next'] && empty($catalog['next']['locked']))<a href="{{ $catalog['next']['path'] }}">Следующее: {{ $catalog['next']['title'] }}</a>@endif</nav>

@endif
</div>
