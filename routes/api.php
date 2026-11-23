<?php

use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\EmailTemplateController;
use Illuminate\Support\Facades\Route;

Route::post('/classify', [ClassificationController::class, 'classify']);
Route::post('/generate-email', [EmailTemplateController::class, 'generate']);