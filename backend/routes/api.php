<?php

use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/', function () {
        return response()->json(['message' => 'API OK']);
    });

    Route::prefix('billing')->group(function () {
        Route::get('{id}', [BillingController::class, 'show']);
        Route::post('{id}/pay', [BillingController::class, 'pay']);
    });

});