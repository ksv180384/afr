<?php

namespace App\Http\Controllers\Admin\DailySession;

use App\Helpers\Helper;
use App\Http\Requests\Admin\DailySession\EditorRequest;
use App\Services\Learning\PronunciationCatalog;
use App\Services\Learning\PronunciationRecordings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

final class PronunciationController
{
    public function index(EditorRequest $request, PronunciationCatalog $catalog): Response
    {
        abort_unless(app()->environment('local'), 404);

        return Inertia::render('DailySession/Pronunciation', [
            'authUser' => Helper::getUserData(),
            'rows' => fn () => array_values(array_map(fn ($row) => $row + ['files' => PronunciationRecordings::files($row['key'])], $catalog->rows())),
        ]);
    }

    public function generate(EditorRequest $request, PronunciationCatalog $catalog): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);
        $input = $request->validate(['key' => ['sometimes', 'required', 'regex:/\A[a-f0-9]{64}\z/'],
            'mode' => 'required|in:missing,replace', 'voice' => 'sometimes|required|in:lea,paul']);
        $key = $input['key'] ?? null;
        $replace = $input['mode'] === 'replace';
        abort_if(($replace || isset($input['voice'])) && $key === null, 422);
        $selected = isset($input['voice']) ? [$input['voice']] : PronunciationRecordings::VOICES;
        $rows = $catalog->rows();
        if ($key !== null) {
            abort_unless(isset($rows[$key]), 404);
            $rows = [$rows[$key]];
        }
        $items = [];
        foreach ($rows as $row) {
            $voices = $replace ? $selected : array_values(array_diff(
                $selected, array_keys(PronunciationRecordings::files($row['key']))
            ));
            if ($voices) {
                $items[] = ['text' => PronunciationRecordings::text($row['text']), 'voices' => $voices];
            }
        }

        return response()->json($this->worker('post', ['items' => $items, 'replace' => $replace]), 202);
    }

    public function status(EditorRequest $request, PronunciationCatalog $catalog): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);
        $keys = $request->validate(['keys' => 'sometimes|array|max:50', 'keys.*' => ['string', 'regex:/\A[a-f0-9]{64}\z/']])['keys'] ?? [];
        $queue = $this->worker('get', ['keys' => implode(',', $keys)]);
        $files = [];
        foreach ($keys as $key) {
            $files[$key] = PronunciationRecordings::files($key);
        }

        $catalogKeys = array_keys($catalog->rows());
        $ready = 0;
        $voiceCount = count(PronunciationRecordings::VOICES);
        foreach ($catalogKeys as $key) {
            $recordings = $files[$key] ?? PronunciationRecordings::files($key);
            if (count($recordings) === $voiceCount) {
                $ready++;
            }
        }

        return response()->json($queue + ['files' => $files, 'coverage' => ['ready' => $ready, 'total' => count($catalogKeys)]]);
    }

    private function worker(string $method, array $data): array
    {
        try {
            $result = Http::connectTimeout(2)->timeout(15)->$method('http://pronunciation:8080/jobs', $data);
        } catch (ConnectionException) {
            abort(503, 'Генератор недоступен. Запустите локальный сервис командой docker compose --profile audio up -d pronunciation.');
        }
        abort_unless($result->successful(), 503, 'Локальный генератор не смог обработать запрос.');

        return $result->json();
    }
}
