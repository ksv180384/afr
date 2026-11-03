<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\GuestSyncRequest;
use App\Services\Learning\GuestSyncService;

class GuestSyncController extends Controller
{
    public function __invoke(GuestSyncRequest $request, GuestSyncService $service)
    {
        return response()->json($service->sync($request->user(), $request->validated()));
    }
}
