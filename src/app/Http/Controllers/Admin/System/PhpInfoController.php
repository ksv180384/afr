<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class PhpInfoController extends Controller
{
    public function __invoke(): Response
    {
        ob_start();
        phpinfo();
        $phpInfo = (string) ob_get_clean();

        return response($phpInfo)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
