<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FmsAlertController;

Route::post('/fms/alerts', [FmsAlertController::class, 'handle']);
Route::get('/healthz',     [FmsAlertController::class, 'healthz']);

// Test routes for WhatsApp alert simulation
Route::post('/test/whatsapp/{alertType}', [FmsAlertController::class, 'testAlert']);
Route::get('/test/whatsapp', [FmsAlertController::class, 'listTestAlerts']);
