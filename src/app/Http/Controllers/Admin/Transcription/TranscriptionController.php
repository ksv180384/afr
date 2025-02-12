<?php

namespace App\Http\Controllers\Admin\Transcription;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Services\App\TranscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TranscriptionController extends Controller
{
    public function index()
    {
        $authUser = Helper::getUserData();

        return Inertia::render('Transcription/Transcription', [
            'authUser' => $authUser,
        ]);
    }

    public function train(TranscriptionService $transcriptionService)
    {
        $resTrain = $transcriptionService->train();

        return response()->json([
            'message' => $resTrain,
        ]);
    }

    public function transcribe(Request $request, TranscriptionService $transcriptionService)
    {
        $resTrain = $transcriptionService->transcribe($request->text);

        return response()->json([
            'transcription' => $resTrain,
        ]);
    }
}
