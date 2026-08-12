<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class GamesController extends Controller
{
    public function index()
    {
        return Inertia::render('Games/Index', ['authUser' => Helper::getUserData()]);
    }

    public function fallingTranslations()
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return Inertia::render('Games/FallingTranslations', ['authUser' => Helper::getUserData()]);
    }
}
