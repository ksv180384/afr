<?php

namespace App\Http\Controllers\App;

use App\Services\Learning\PronunciationRecordings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PronunciationLookupController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate(['texts' => 'required|array|list|max:50', 'texts.*' => 'required|string|max:20000']);

        return response()->json(['files' => array_map(
            fn ($text) => PronunciationRecordings::files(PronunciationRecordings::key($text)),
            $input['texts']
        )]);
    }
}
