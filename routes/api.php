<?php

/**
 * BdPayment API Routes
 *
 * Registers API endpoints for Bkash and Nagad payment gateways.
 * Each gateway has endpoints for creating payments, executing payments, and handling callbacks.
 *
 * @package RmdMostakim\BdPayment
 */

use Illuminate\Support\Facades\Route;
use RmdMostakim\BdPayment\Http\Controllers\Api\Bkash\PaymentController as BkashPaymentController;
use RmdMostakim\BdPayment\Http\Controllers\Api\Nagad\PaymentController as NagadPaymentController;
use RmdMostakim\BdPayment\Http\Controllers\Api\Sslcommerz\PaymentController as SslcommerzPaymentController;

// Group all payment gateway routes under /api/gateway
Route::middleware(['api'])->prefix('api/gateway')
    ->name('gateway.')
    ->group(function () {
        // Bkash payment routes
        Route::prefix('bkash')
            ->name('bkash.')
            ->controller(BkashPaymentController::class)
            ->group(function () {
                Route::post('create', 'create');   // Create a Bkash payment
                Route::post('execute', 'execute'); // Execute a Bkash payment
                Route::get('callback', 'callback'); // Bkash payment callback (redirects to frontend)
            });
        // Nagad payment routes
        Route::prefix('nagad')
            ->name('nagad.')
            ->controller(NagadPaymentController::class)
            ->group(function () {
                Route::post('create', 'create');   // Create a Nagad payment
                Route::get('callback', 'callback'); // Nagad payment callback (redirects to frontend)
            });
        // SSLCommerz payment routes
        Route::prefix('sslcommerz')
            ->name('sslcommerz.')
            ->controller(SslcommerzPaymentController::class)
            ->group(function () {
                Route::post('create', 'create');   // Create a SSLCommerz payment
                Route::post('callback', 'callback'); // SSLCommerz payment callback (redirects to frontend)
            });
    });
