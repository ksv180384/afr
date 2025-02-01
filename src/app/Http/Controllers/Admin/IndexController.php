<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class IndexController extends Controller
{
    public function index()
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Index', [
            'authUser' => $authUser,
        ]);
    }
}
