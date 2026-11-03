<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PracticePreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(app()->environment('local'), 404);
        $sessions = [];
        foreach (['pre-a1-u01-examples', 'task-types'] as $template) {
            $package = json_decode(file_get_contents(resource_path('learning/templates/'.$template.'.json')), true, 512, JSON_THROW_ON_ERROR);
            array_push($sessions, ...$package['sessions']);
        }

        return Inertia::render('Practice/Preview', [
            'sessions' => $sessions,
            'authUser' => Helper::getUserData(),
            'seo' => ['title' => 'Предпросмотр занятия', 'noIndex' => true],
        ])->toResponse($request)->withHeaders(['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
