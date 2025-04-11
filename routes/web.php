<?php

use App\Http\Controllers\VietQRController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/hook/{key}', [WebhookController::class, 'handle']);
Route::post('/hook/{key}', [WebhookController::class, 'handle']);

Route::post('/telegram-webhook', [WebhookController::class, 'handleTelegram']);
Route::post('/admin-webhook', [WebhookController::class, 'handleAdmin']);

Route::post('/vqr/api/token_generate', [WebhookController::class, 'handleTokenGenerate']);
Route::post('/vqr/bank/api/transaction-sync', [WebhookController::class, 'handleTransactionSync']);

Route::post('/admin-webhook', [WebhookController::class, 'handleAdmin']);

Route::get('/store-qr', [VietQRController::class, 'getStoreQR']);



