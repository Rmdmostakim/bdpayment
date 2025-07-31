<?php

use Illuminate\Support\Facades\Route;
use RmdMostakim\BdPayment\Http\Controllers\Api\Bkash\PaymentController;

Route::middleware(['api'])->prefix('api/gateway')
    ->name('gateway.')
    ->group(function () {
        Route::prefix('bkash')
            ->name('bkash.')
            ->controller(PaymentController::class)
            ->group(function () {
                Route::post('create', 'create');
                Route::post('execute', 'execute');
                Route::post('callback', 'callback');
            });
    });
