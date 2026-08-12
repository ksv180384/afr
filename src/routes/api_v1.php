<?php
use Illuminate\Support\Facades\Route;



use App\Http\Controllers\Api\V1\FallingTranslationsController;

Route::prefix('games/falling-translations')->group(function () {
    Route::post('sessions', [FallingTranslationsController::class, 'start'])->middleware('throttle:20,1');
    Route::post('sessions/{session}/next', [FallingTranslationsController::class, 'next'])->middleware('throttle:120,1');
    Route::post('sessions/{session}/answer', [FallingTranslationsController::class, 'answer'])->middleware('throttle:120,1');
    Route::post('sessions/{session}/finish', [FallingTranslationsController::class, 'finish'])->middleware('throttle:20,1');
});
