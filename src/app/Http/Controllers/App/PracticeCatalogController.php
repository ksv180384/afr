<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\LearningUrlAlias;
use App\Services\Learning\PracticeUrls;
use App\Services\Learning\PublicPracticeCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PracticeCatalogController extends Controller
{
    public function __invoke(Request $request, PublicPracticeCatalog $catalog, ?string $level = null, ?string $unit = null, ?string $session = null)
    {
        try {
            return $this->render($request, $catalog, $level, $unit, $session);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);

            return response('Материалы временно недоступны. Попробуйте позже.', 503)
                ->withHeaders(['Retry-After' => '60', 'X-Robots-Tag' => 'noindex, follow', 'Cache-Control' => 'no-store']);
        }
    }

    private function render(Request $request, PublicPracticeCatalog $catalog, ?string $level, ?string $unit, ?string $session)
    {
        abort_unless(config('daily_practice.enabled'), 404);
        $alias = LearningUrlAlias::where('old_path', '/'.$request->path())->first();
        if ($alias) {
            if ($alias->session_id) {
                $target = LearningSession::with('unit.levelDefinition', 'activeVersion')->findOrFail($alias->session_id);
                $catalog->assertSession($target);
                $path = PracticeUrls::session($target);
            } else {
                $target = LearningUnit::with('levelDefinition')->findOrFail($alias->unit_id);
                $catalog->assertUnit($target);
                $path = PracticeUrls::unit($target);
            }

            return redirect(PracticeUrls::absolute($path), 301);
        }
        abort_if($request->has('page') && $request->query('page') !== '1', 404);
        $data = $catalog->page($level, $unit, $session);
        $queries = array_filter($request->query(), fn ($value, $key) => ! str_starts_with($key, 'utm_') && ! in_array($key, ['gclid', 'fbclid', 'page']), ARRAY_FILTER_USE_BOTH);
        ksort($queries);
        if ($queries) {
            $data['seo']['noIndex'] = true;
            $data['seo']['canonicalUrl'] .= '?'.http_build_query($queries);
        }
        $outline = app(\App\Services\Learning\LearningAccessService::class)->outline();
        $accessService = app(\App\Services\Learning\LearningAccessService::class);
        $states = $accessService->states($outline, $request->user() ? $accessService->completed($request->user()) : [], $request->user() ? $accessService->profileLevel($request->user()) : 'PRE_A1');
        $data = $accessService->decorate($data, $states);
        if ($request->user() && $data['access']['locked']) {
            return redirect('/practice')->with('practice_access_notice', $data['access']['reason']);
        }
        $authUser = Helper::getUserData();

        return Inertia::render('Practice/Index', ['catalog' => $data, 'accessOutline' => $outline, 'accessNotice' => $request->session()->get('practice_access_notice'), 'public_practice' => true, 'authUser' => $authUser, 'auth' => ['user' => $authUser]])
            ->rootView('practice')->toResponse($request)->withHeaders([
                'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => $data['seo']['noIndex'] ? 'noindex, follow' : 'index, follow',
            ]);
    }
}
