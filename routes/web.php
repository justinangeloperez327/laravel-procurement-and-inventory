<?php

use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/documents/versions/{version}/download', DocumentDownloadController::class)
    ->middleware(['auth', 'signed', 'throttle:60,1'])
    ->name('documents.download');
