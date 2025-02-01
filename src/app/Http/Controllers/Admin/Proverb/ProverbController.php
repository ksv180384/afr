<?php

namespace App\Http\Controllers\Admin\Proverb;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Proverb\CreateProverbRequest;
use App\Http\Requests\Admin\Proverb\UpdateProverbRequest;
use App\Http\Resources\Admin\Proverb\ProverbResource;
use App\Http\Resources\PaginateResource;
use App\Services\App\ProverbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class ProverbController extends Controller
{
    /**
     * @param ProverbService $proverbService
     * @return \Inertia\Response
     */
    public function index(ProverbService $proverbService): Response
    {
        $authUser = Helper::getUserData();
        $proverbs = $proverbService->getProverbsPagination(ProverbService::PROVERB_PAGINATION);

        return Inertia::render('Proverb/Proverbs', [
            'authUser' => $authUser,
            'proverbs' => ProverbResource::collection($proverbs->items()),
            'pagination' => PaginateResource::make($proverbs),
        ]);
    }

    /**
     * @return Response
     */
    public function create(Request $request): Response
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Proverb/ProverbCreate', [
            'authUser' => $authUser,
            'redirect' => URL::previous(),
        ]);
    }

    /**
     * @param CreateProverbRequest $request
     * @param ProverbService $proverbService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateProverbRequest $request, ProverbService $proverbService): RedirectResponse
    {
        $proverbData = $request->validated();

        $proverbService->create($proverbData);

        if($request->redirect && URL::isValidUrl($request->redirect)){
            return Redirect::to($request->redirect);
        }

        return Redirect::route('admin.proverbs');
    }

    /**
     * @param int $id
     * @param ProverbService $proverbService
     * @return Response
     */
    public function edit(int $id, ProverbService $proverbService): Response
    {
        $authUser = Helper::getUserData();
        $proverb = $proverbService->getById($id);

        return Inertia::render('Proverb/ProverbEdit', [
            'authUser' => $authUser,
            'proverb' => $proverb,
            'redirect' => URL::previous(),
        ]);
    }

    public function update(int $id, UpdateProverbRequest $request, ProverbService $proverbService): RedirectResponse
    {
        $proverbData = $request->validated();

        $proverbService->update($id, $proverbData);

        if($request->redirect && URL::isValidUrl($request->redirect)){
            return Redirect::to($request->redirect);
        }

        return Redirect::route('admin.proverbs');
    }
}
