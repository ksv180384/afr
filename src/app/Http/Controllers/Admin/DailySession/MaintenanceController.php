<?php

namespace App\Http\Controllers\Admin\DailySession;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DailySession\ContentOperationRequest;
use App\Services\Learning\ContentMaintenanceService;

class MaintenanceController extends Controller
{
    public function save(ContentOperationRequest $request, int $version, ContentMaintenanceService $service)
    {
        $data = $request->validate(['content' => ['required', 'array'], 'checksum' => ['required', 'string', 'size:64']]);

        return response()->json($service->save($request->user(), $version, $data['content'], $data['checksum']));
    }

    public function delete(ContentOperationRequest $request, string $kind, string $id, ContentMaintenanceService $service)
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);

        return response()->json($service->delete($request->user(), $kind, $id));
    }
}
