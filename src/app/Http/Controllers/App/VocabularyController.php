<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\VocabularyRequest;
use App\Models\Learning\PersonalVocabulary;
use App\Models\User\User;
use App\Services\Learning\VocabularyTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VocabularyController extends Controller
{
    public function page(Request $request)
    {
        return Inertia::render('Daily/Vocabulary', ['authUser' => Helper::getUserData(), 'enabled' => (bool) config('daily_practice.enabled')])
            ->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function index(VocabularyRequest $request, VocabularyTestService $service)
    {
        return response()->json($service->listing($request->user(), $request->validated()));
    }

    public function plan(VocabularyRequest $request, VocabularyTestService $service)
    {
        $words = $service->filtered($request->user(), $request->validated());
        $selfCheck = $words->filter(fn ($word) => ! $service->items($service->source($word)))->map(fn ($word) => $service->data($word))->values();
        $remaining = $words->filter(fn ($word) => $service->items($service->source($word)))->values();
        $ids = [];
        while ($remaining->isNotEmpty()) {
            $block = $service->selection($remaining, 10, ($request->validated('filter') ?? '') === 'errors');
            $ids = [...$ids, ...$block->pluck('id')->all()];
            $remaining = $remaining->whereNotIn('id', $block->pluck('id'))->values();
        }

        return response()->json(['word_ids' => $ids, 'self_check_words' => $selfCheck, 'total' => count($ids)]);
    }

    public function mark(VocabularyRequest $request, string $word, VocabularyTestService $service)
    {
        $result = DB::transaction(function () use ($request, $word, $service) {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $row = PersonalVocabulary::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($word);
            $row->update(['needs_review' => $request->validated('needs_review'), 'native_revision' => $row->native_revision + 1]);
            $row->load('sources.sessionVersion.session');

            return $service->data($row);
        });

        return response()->json(['word' => $result]);
    }
}
