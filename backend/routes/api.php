<?php

use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['message' => 'ok']));

Route::get('/billing/{id}', [BillingController::class, 'show'])->whereNumber('id');
Route::post('/billing/{id}/pay', [BillingController::class, 'pay'])->whereNumber('id');
