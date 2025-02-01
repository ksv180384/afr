<?php

namespace App\Http\Controllers\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\Post\PostResource;
use App\Http\Resources\PaginateResource;
use App\Services\App\Post\PostService;
use App\Services\App\ProverbService;
use App\Services\App\WordService;
use Inertia\Inertia;

class IndexController extends Controller
{
    public function index(
        PostService $postService,
        WordService $wordService,
        ProverbService $proverbService,
    )
    {
        $posts = $postService->getPostPaginate(PostService::POSTS_PAGINATE);

        $words = $wordService->wordsRandom();
        $proverb = $proverbService->proverbRandomOne();
        $authUser = Helper::getUserData();

        return Inertia::render('Index/Index', [
            'words' => $words,
            'proverb' => $proverb,
            'authUser' => $authUser,
            'posts' => PostResource::collection($posts->items()),
            'paginate' => PaginateResource::make($posts),
        ]);
    }
}

