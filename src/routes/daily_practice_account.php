<?php

use App\Http\Controllers\App\DailyPracticeController;
use App\Http\Controllers\App\GuestPracticeController;
use App\Http\Controllers\App\GuestSyncController;
use App\Http\Controllers\App\VocabularyController;
use App\Http\Middleware\DailyApiMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/daily/vocabulary', [VocabularyController::class, 'page'])->name('daily.vocabulary');
Route::get('/daily/progress', [DailyPracticeController::class, 'page'])->name('daily.progress');
Route::post('/daily/api/reports', \App\Http\Controllers\App\PracticeReportController::class)->middleware([DailyApiMiddleware::class, 'auth', 'throttle:6,1'])->name('daily.api.reports');
Route::get('/daily', [DailyPracticeController::class, 'page'])->name('daily');
Route::get('/daily/run/{run}', [DailyPracticeController::class, 'page'])->whereUuid('run')->name('daily.run');
Route::get('/daily/guest/{run}', [DailyPracticeController::class, 'page'])->whereUuid('run')->name('daily.guest');
Route::prefix('daily/api/content')->name('daily.content.')->middleware([DailyApiMiddleware::class, 'throttle:120,1'])->group(function (): void {
    Route::get('/', [GuestPracticeController::class, 'index'])->name('index');
    Route::get('{session}', [GuestPracticeController::class, 'show'])->where('session', '[A-Za-z0-9_-]+')->name('show');
    Route::get('{session}/media/{media}', [GuestPracticeController::class, 'media'])->where('session', '[A-Za-z0-9_-]+')->whereUuid('media')->name('media');
});
Route::prefix('daily/api')->name('daily.api.')->middleware([DailyApiMiddleware::class, 'auth', 'throttle:120,1'])->group(function (): void {
    Route::get('vocabulary', [VocabularyController::class, 'index'])->name('vocabulary');
    Route::get('vocabulary/test-plan', [VocabularyController::class, 'plan'])->name('vocabulary.plan');
    Route::patch('vocabulary/{word}', [VocabularyController::class, 'mark'])->whereNumber('word')->name('vocabulary.mark');
    Route::post('guest-sync', GuestSyncController::class)->middleware('throttle:12,1,practice-sync')->name('guest-sync');
    Route::get('today', [DailyPracticeController::class, 'today'])->name('today');
    Route::get('profile', [DailyPracticeController::class, 'profile'])->name('profile');
    Route::patch('profile', [DailyPracticeController::class, 'profileUpdate'])->name('profile.update');
    Route::get('history', [DailyPracticeController::class, 'history'])->name('history');
    Route::post('runs', [DailyPracticeController::class, 'start'])->name('start');
    Route::get('runs/{run}', [DailyPracticeController::class, 'show'])->whereUuid('run')->name('show');
    foreach (['answer' => 'answers', 'help' => 'help', 'pause' => 'pause', 'resume' => 'resume', 'finish' => 'finish', 'advance' => 'advance', 'introduction' => 'introduction'] as $action => $path) {
        Route::post('runs/{run}/'.$path, [DailyPracticeController::class, $action])->whereUuid('run')->name($action);
    }
    Route::get('runs/{run}/media/{media}', [DailyPracticeController::class, 'media'])->whereUuid('run')->whereUuid('media')->name('media');
});
