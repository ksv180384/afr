<?php

namespace App\Http\Controllers\App\Widget;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class WidgetController extends Controller
{
    public function player()
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Player/Player', [
            'authUser' => $authUser,
        ]);
    }

    public function learningWrite()
    {
        $authUser = Helper::getUserData();

        return Inertia::render('LearningWrite/LearningWrite', [
            'authUser' => $authUser,
        ]);
    }

    public function checkYourself()
    {
        $authUser = Helper::getUserData();

        return Inertia::render('CheckYourself/CheckYourself', [
            'authUser' => $authUser,
        ]);
    }
}
