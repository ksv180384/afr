@if($notes)<ul class="practice-notes">@foreach($notes as $note)<li><span lang="fr">{{ $note['fr'] }}</span> · {{ $note['transcription_ru'] }}</li>@endforeach</ul>@endif
