<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Learning\LearningAccessService;
use App\Services\Learning\PracticeHomeRoute;
use App\Services\Learning\PublicPracticeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticeRouteController extends Controller
{
    public function search(Request $request, LearningAccessService $access, PracticeHomeRoute $route): JsonResponse
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'completed' => ['sometimes', 'boolean']]);
        $actor = $request->user();
        $outline = $access->outline();
        $states = $access->states($outline, $access->completed($actor), $access->profileLevel($actor));

        return response()->json([
            'owner_user_id' => $actor->id,
            ...$route->search($outline, $states, trim($input['q'] ?? ''), (bool) ($input['completed'] ?? false)),
        ]);
    }

    public function reference(Request $request, PublicPracticeCatalog $catalog, LearningAccessService $access): JsonResponse
    {
        $input = $request->validate(['path' => ['required', 'string', 'max:400', 'regex:~^/practice/[a-z0-9-]+/[a-z0-9-]+(?:/[a-z0-9-]+)?$~D']]);
        $path = $input['path'];
        [, , $level, $unit, $session] = array_pad(explode('/', $path), 5, null);
        if ($request->user()) {
            $group = $session ? 'sessions' : 'units';
            $state = collect($access->forUser($request->user())[$group])->firstWhere('path', $path);
            abort_unless($state && ! $state['locked'], 404);
        }

        $page = $catalog->page($level, $unit, $session);
        $lesson = $page['session'];

        return response()->json([
            'title' => $page['title'],
            'summary' => $page['summary'],
            'outcomes' => $lesson ? array_column($lesson['objectives'], 'title') : $page['outcomes'],
            'notes' => $lesson['pronunciation_notes'] ?? $page['notes'],
            'explanation' => $lesson['public_page']['explanation_blocks'][0] ?? null,
        ]);
    }
}
