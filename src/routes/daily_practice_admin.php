<?php

use App\Http\Controllers\Admin\DailySession\ContentController;
use App\Http\Controllers\Admin\DailySession\EditorController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/daily-practice')->name('admin.daily-practice.')->middleware(['auth', 'verified', 'is-admin', 'throttle:120,1'])->group(function (): void {
    Route::get('/', [EditorController::class, 'index'])->name('index');
    Route::post('sessions/{session}/rename', [EditorController::class, 'renameSession'])->whereNumber('session')->name('sessions.rename');
    Route::post('units/{unit}/rename', [EditorController::class, 'renameUnit'])->whereNumber('unit')->name('units.rename');
    Route::get('catalog', [EditorController::class, 'catalog'])->name('catalog');
    Route::get('reports', [EditorController::class, 'reports'])->name('reports');
    Route::post('reports', [EditorController::class, 'storeReport'])->name('reports.store');
    Route::post('reports/{report}', [EditorController::class, 'updateReport'])->whereNumber('report')->name('reports.update');
    Route::get('media', [EditorController::class, 'media'])->name('media');
    Route::post('media', [EditorController::class, 'storeMedia'])->name('media.store');
    Route::post('media/{media}/approve', [EditorController::class, 'approveMedia'])->whereUuid('media')->name('media.approve');
    Route::get('media/{media}/file', [EditorController::class, 'mediaFile'])->whereUuid('media')->name('media.file');
    Route::get('versions/{version}', [EditorController::class, 'show'])->whereNumber('version')->name('versions.show');
    Route::get('versions/{version}/preview', [EditorController::class, 'preview'])->whereNumber('version')->name('versions.preview');
    Route::post('imports/preview', [ContentController::class, 'preview'])->middleware('throttle:6,1,practice-import')->name('imports.preview');
    Route::post('imports/{batch}/commit', [ContentController::class, 'commit'])->whereUuid('batch')->name('imports.commit');
    Route::get('imports/{batch}/export', [ContentController::class, 'exportBatch'])->whereUuid('batch')->name('imports.export');
    Route::get('versions/{version}/export', [ContentController::class, 'exportVersion'])->whereNumber('version')->name('versions.export');
    Route::post('versions/{version}/review', [ContentController::class, 'reviewVersion'])->whereNumber('version')->name('versions.review');
    Route::post('versions/{version}/publish', [ContentController::class, 'publishVersion'])->whereNumber('version')->name('versions.publish');
    Route::post('versions/{version}/archive', [ContentController::class, 'archiveVersion'])->whereNumber('version')->name('versions.archive');
    Route::post('versions/publish', [ContentController::class, 'publishMany'])->name('versions.publish-many');
    Route::post('sessions/archive', [ContentController::class, 'archiveSessions'])->name('sessions.archive');
    Route::get('catalog/{revision}/export', [ContentController::class, 'exportCatalog'])->whereNumber('revision')->name('catalog.export');
    Route::get('curricula/{curriculum}/export', [ContentController::class, 'exportCurriculum'])->whereNumber('curriculum')->name('curricula.export');
    Route::get('catalog/{revision}/diff', [ContentController::class, 'diff'])->whereNumber('revision')->name('catalog.diff');
    Route::post('catalog/{revision}/review', [ContentController::class, 'reviewCatalog'])->whereNumber('revision')->name('catalog.review');
    Route::post('catalog/{revision}/publish', [ContentController::class, 'publishCatalog'])->whereNumber('revision')->name('catalog.publish');
    Route::post('curricula/{curriculum}/review', [ContentController::class, 'reviewCurriculum'])->whereNumber('curriculum')->name('curricula.review');
    Route::post('curricula/{curriculum}/publish', [ContentController::class, 'publishCurriculum'])->whereNumber('curriculum')->name('curricula.publish');
    Route::post('curricula/{curriculum}/archive', [ContentController::class, 'archiveCurriculum'])->whereNumber('curriculum')->name('curricula.archive');
    Route::post('versions/status', [ContentController::class, 'changeStatus'])->name('versions.status');
    Route::post('catalog/{revision}/publish-direct', [ContentController::class, 'publishCatalogDirect'])->whereNumber('revision')->name('catalog.publish-direct');
    Route::post('curricula/{curriculum}/publish-direct', [ContentController::class, 'publishCurriculumDirect'])->whereNumber('curriculum')->name('curricula.publish-direct');
    Route::get('schema', [ContentController::class, 'schema'])->name('schema');
    Route::get('templates/{template}', [ContentController::class, 'template'])->whereIn('template', ['pre-a1-u01', 'pre-a1-u01-v2', 'task-types'])->name('templates');
});
