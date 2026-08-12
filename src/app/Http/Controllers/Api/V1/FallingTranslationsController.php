<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Services\App\FallingTranslationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FallingTranslationsController extends Controller
{
    public function start(Request $request, FallingTranslationsService $game): JsonResponse
    {
        $session = $game->start($request->user()?->id);
        return response()->json(['session_id' => $session->id, 'question' => $game->question($session), 'stats' => $game->stats($session)]);
    }

    public function next(GameSession $session, FallingTranslationsService $game): JsonResponse
    {
        $session = $game->next($session);
        return response()->json(['question' => $game->question($session), 'stats' => $game->stats($session)]);
    }

    public function answer(Request $request, GameSession $session, FallingTranslationsService $game): JsonResponse
    {
        $data = $request->validate([
            'option_id' => ['nullable', 'integer'],
            'response_ms' => ['required', 'integer', 'min:0', 'max:40000'],
            'wrong_attempts' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);
        return response()->json($game->answer($session, $data['option_id'] ?? null, $data['response_ms'], $data['wrong_attempts'] ?? 0));
    }

    public function finish(GameSession $session, FallingTranslationsService $game): JsonResponse
    {
        $session = $game->finish($session);
        return response()->json(['stats' => $game->stats($session)]);
    }
}
