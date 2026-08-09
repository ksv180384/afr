<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\KaraokeUploadLog;
use App\Models\Player\PlayerSongs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KaraokeUploadLogController extends Controller
{
    private const DURATION_TOLERANCE_SECONDS = 2;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'song_id' => ['required', 'integer', 'exists:player_songs,id'],
            'song_title' => ['required', 'string', 'max:255'],
            'song_artist' => ['required', 'string', 'max:255'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_duration_seconds' => ['required', 'numeric', 'min:0'],
            'db_duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $song = PlayerSongs::with('lyricsVersions')->findOrFail($validated['song_id']);
        if ($song->hidden) {
            return response()->json(['message' => 'Song not found'], 404);
        }

        $durations = collect([$song->duration])
            ->concat($song->lyricsVersions->pluck('duration'))
            ->filter(fn ($duration) => $duration !== null)
            ->map(fn ($duration) => (float) $duration * 60);
        $durationMatched = $durations->contains(
            fn (float $duration) => abs($duration - (float) $validated['file_duration_seconds']) <= self::DURATION_TOLERANCE_SECONDS,
        );

        $uploadToken = $durationMatched ? null : Str::random(64);
        $log = KaraokeUploadLog::create([
            'song_id' => $song->id,
            'song_title' => $song->title,
            'song_artist' => $song->artist_name,
            'file_name' => $validated['file_name'],
            'file_duration_seconds' => round((float) $validated['file_duration_seconds'], 2),
            'db_duration_seconds' => $validated['db_duration_seconds'] ?? null,
            'duration_matched' => $durationMatched,
            'upload_token' => $uploadToken,
            'user_id' => $durationMatched ? Auth::id() : null,
        ]);

        return response()->json([
            'ok' => true,
            'duration_matched' => $durationMatched,
            'upload_required' => ! $durationMatched,
            'log_id' => $log->id,
            'upload_token' => $uploadToken,
        ]);
    }

    public function upload(KaraokeUploadLog $log, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_token' => ['required', 'string', 'size:64'],
            'audio' => ['required', 'file', 'max:46080'],
        ]);

        abort_if($log->duration_matched || $log->file_path || ! $log->upload_token, 404);
        abort_unless(hash_equals($log->upload_token, $validated['upload_token']), 404);

        $audio = $request->file('audio');
        $extension = strtolower((string) $audio->getClientOriginalExtension());
        if (! in_array($extension, ['mp3', 'm4a', 'mp4', 'ogg', 'wav', 'webm'], true)) {
            throw ValidationException::withMessages(['audio' => 'Неподдерживаемый формат аудиофайла.']);
        }

        $filePath = $audio->store('karaoke-uploads');

        try {
            $log->update([
                'file_path' => $filePath,
                'file_mime_type' => $audio->getMimeType(),
                'file_size' => $audio->getSize(),
                'upload_token' => null,
            ]);
        } catch (\Throwable $exception) {
            Storage::delete($filePath);
            throw $exception;
        }

        return response()->json(['ok' => true]);
    }
}
