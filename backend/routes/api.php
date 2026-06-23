<?php

use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'ok']);
});

Route::get('/billing/{id}', [BillingController::class, 'show']);
Route::post('/billing/{id}/pay', [BillingController::class, 'pay']);
Route::get('/billings', [BillingController::class, 'index']);
