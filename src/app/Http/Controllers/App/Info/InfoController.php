<?php

namespace App\Http\Controllers\App\Info;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\App\ProverbService;
use App\Services\App\WordService;
use Inertia\Inertia;
use Inertia\Response;

class InfoController extends Controller
{
    /**
     * @param WordService $wordService
     * @param ProverbService $proverbService
     * @return Response
     */
    public function termsUser(
        WordService $wordService,
        ProverbService $proverbService,
    ): Response
    {
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $authUser = Helper::getUserData();

        return Inertia::render('Info/TermsUser', [
            'words' => $words,
            'proverb' => $proverb,
            'authUser' => $authUser,
        ]);
    }

    /**
     * @param WordService $wordService
     * @param ProverbService $proverbService
     * @return Response
     */
    public function privacyPolicy(
        WordService $wordService,
        ProverbService $proverbService,
    ): Response
    {
        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $authUser = Helper::getUserData();

        return Inertia::render('Info/PrivacyPolicy', [
            'words' => $words,
            'proverb' => $proverb,
            'authUser' => $authUser,
        ]);
    }
}
