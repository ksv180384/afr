<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\KaraokeUploadLog;
use App\Models\Player\PlayerSongs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KaraokeUploadLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'song_id' => ['required', 'integer', 'exists:player_songs,id'],
            'song_title' => ['required', 'string', 'max:255'],
            'song_artist' => ['required', 'string', 'max:255'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_duration_seconds' => ['required', 'numeric', 'min:0'],
            'db_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'duration_matched' => ['required', 'boolean'],
        ]);

        $song = PlayerSongs::find($validated['song_id']);
        if ($song && $song->hidden) {
            return response()->json(['message' => 'Song not found'], 404);
        }

        KaraokeUploadLog::create([
            'song_id' => $validated['song_id'],
            'song_title' => $validated['song_title'],
            'song_artist' => $validated['song_artist'],
            'file_name' => $validated['file_name'],
            'file_duration_seconds' => round((float) $validated['file_duration_seconds'], 2),
            'db_duration_seconds' => $validated['db_duration_seconds'] ?? null,
            'duration_matched' => $validated['duration_matched'],
            'user_id' => Auth::id(),
        ]);

        return response()->json(['ok' => true]);
    }
}
