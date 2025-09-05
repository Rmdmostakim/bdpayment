<?php

/**
 * Trait InteractsWithFrontend
 *
 * Provides helper for redirecting to frontend callback URL after payment.
 * Appends invoice, status, and message as query parameters.
 *
 * @package RmdMostakim\BdPayment\Traits
 */

namespace RmdMostakim\BdPayment\Traits;

trait InteractsWithFrontend
{
    /**
     * Build the frontend callback URL with payment status and message.
     *
     * @param string $invoice
     * @param string $status
     * @param string $message
     * @return string
     */
    protected function frontendCallback(string $invoice, string $status, string $message): string
    {
        $frontendCallbackUrl = config('bdpayment.frontend_success_url');

        return $frontendCallbackUrl . '?' . http_build_query([
            'invoice'   => $invoice,
            'status'  => $status,
            'message' => $message,
        ]);
    }
}
