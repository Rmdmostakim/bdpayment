<?php

use Illuminate\Support\Facades\Route;
use RmdMostakim\BdPayment\Http\Controllers\Web\Bkash\PaymentController;

Route::middleware(['web'])->prefix('gateway')
    ->name('gateway.')
    ->group(function () {
        Route::prefix('bkash')
            ->name('bkash.')
            ->controller(PaymentController::class)
            ->group(function () {
                Route::post('/', 'index')->name('index');
                Route::post('create', 'create')->name('create');
                Route::post('execute', 'execute')->name('execute');
                Route::get('callback', 'callback')->name('callback');
            });
    });
