<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FmsAlertController;

Route::post('/fms/alerts', [FmsAlertController::class, 'handle']);
Route::get('/healthz',     [FmsAlertController::class, 'healthz']);

