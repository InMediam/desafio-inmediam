<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'ok']);
});

Route::get('/billing', [BillingController::class, 'index']);
Route::get('/billing/{id}', [BillingController::class, 'show']);
Route::patch('/billing/{id}/customer-address', [BillingController::class, 'updateCustomerAddress']);
Route::post('/billing/{id}/pay', [BillingController::class, 'pay']);

Route::post('/webhooks/{gateway}', WebhookController::class)->where('gateway', '[a-z0-9_]+');
