<?php

use App\Http\Controllers\App\PracticeCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/practice', PracticeCatalogController::class)->name('practice');
Route::get('/practice/{level}', PracticeCatalogController::class)->where('level', '[a-z0-9-]+')->name('practice.level');
Route::get('/practice/{level}/{unit}', PracticeCatalogController::class)->where(['level' => '[a-z0-9-]+', 'unit' => '[a-z0-9-]+'])->name('practice.unit');
Route::get('/practice/{level}/{unit}/{session}', PracticeCatalogController::class)->where(['level' => '[a-z0-9-]+', 'unit' => '[a-z0-9-]+', 'session' => '[a-z0-9-]+'])->name('practice.session');
