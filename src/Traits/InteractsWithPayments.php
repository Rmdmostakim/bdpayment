<?php

/**
 * Trait InteractsWithPayments
 *
 * Provides helper methods for filtering payloads, building gateway payloads,
 * storing and updating payment records for Bkash and Nagad.
 *
 * @package RmdMostakim\BdPayment\Traits
 */

namespace RmdMostakim\BdPayment\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use RmdMostakim\BdPayment\Models\Bdpayment;

trait InteractsWithPayments
{
    /**
     * Filter and validate Bkash payment payload.
     * Only 'amount' is required; 'user_id', 'product_id', 'invoice' are optional.
     *
     * @param array $payload
     * @return array
     * @throws ValidationException
     */
    protected function filterBkashPayload(array $payload): array
    {
        $requiredKeys = ['amount'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                Log::error("Bkash create payment failed: missing required key '$key'");
                throw ValidationException::withMessages([
                    $key => "The $key field is required."
                ]);
            }
        }

        $filtered = array_intersect_key($payload, array_flip($requiredKeys));

        // user_id is optional, add if present
        if (array_key_exists("user_id", $payload)) {
            $filtered["user_id"] = $payload["user_id"];
        }
        // product_id is optional, add if present
        if (array_key_exists("product_id", $payload)) {
            $filtered["product_id"] = $payload["product_id"];
        }
        // invoice is optional, add if present
        if (array_key_exists("invoice", $payload)) {
            $filtered["invoice"] = $payload["invoice"];
        }

        return $filtered;
    }

    /**
     * Build the payload for Bkash API.
     * Adds callback URL for web requests or sandbox API requests.
     *
     * @param array $filtered
     * @param string $invoice
     * @return array
     */
    protected function makeBkashPayload(array $filtered, string $invoice): array
    {
        $isApi = Request::is('api/*');
        $mode = config('bdpayment.drivers.bkash.mode', 'sandbox');

        $payload = [
            'mode'                   => '0000',
            'payerReference'         => config('bdpayment.drivers.bkash.merchant_number'),
            'amount'                 => (string) $filtered['amount'],
            'currency'               => 'BDT',
            'intent'                 => 'sale',
            'merchantInvoiceNumber' => $invoice,
        ];

        // Only add callbackURL if:
        // - Web request (not API), OR
        // - API request AND mode is sandbox
        if (!$isApi || ($isApi && $mode === 'sandbox')) {
            $payload['callbackURL'] = filled(config('bdpayment.drivers.bkash.callback_url'))
                ? config('bdpayment.drivers.bkash.callback_url')
                : route('gateway.bkash.callback');
        }

        return $payload;
    }

    /**
     * Filter and validate Nagad payment payload.
     * 'amount', 'transaction_id', 'challenge' are required; others are optional.
     *
     * @param array $payload
     * @return array
     * @throws ValidationException
     */
    protected function filterNagadPayload(array $payload): array
    {
        $requiredKeys = ['amount', 'transaction_id', 'challenge'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                Log::error("Nagad execute payment failed: missing required key '$key'");
                throw ValidationException::withMessages([
                    $key => "The $key field is required."
                ]);
            }
        }

        $filtered = array_intersect_key($payload, array_flip($requiredKeys));
        // user_id is optional, add if present
        if (array_key_exists("user_id", $payload)) {
            $filtered["user_id"] = $payload["user_id"];
        }
        // product_id is optional, add if present
        if (array_key_exists("product_id", $payload)) {
            $filtered["product_id"] = $payload["product_id"];
        }
        // invoice is optional, add if present
        if (array_key_exists("invoice", $payload)) {
            $filtered["invoice"] = $payload["invoice"];
        }

        return $filtered;
    }

    /**
     * Store a payment record in the database.
     * Adds mode and currency to payload.
     *
     * @param array $payload
     * @param string $mode
     * @return string|null Invoice ID or null on failure
     */
    protected function storePayment(array $payload, string $mode = 'bkash'): ?string
    {
        $payload['mode'] = $mode;
        $payload['currency'] = 'BDT';

        try {
            $result = Bdpayment::create($payload);
            return $result->invoice ?? null;
        } catch (\Throwable $e) {
            log::error('Failed to store payment.', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return null;
        }
    }

    /**
     * Update a payment record by invoice.
     * Only updates fillable/castable fields.
     *
     * @param string $invoice
     * @param array $data
     * @return bool
     */
    protected function updatePayment(string $invoice, array $data = []): bool
    {
        try {
            $payment = Bdpayment::where('invoice', $invoice)->firstOrFail();
            $validKeys = array_merge(
                $payment->getFillable(),
                array_keys($payment->getCasts())
            );

            $filteredData = array_filter(
                $data,
                fn($key) => in_array($key, $validKeys),
                ARRAY_FILTER_USE_KEY
            );

            $payment->update($filteredData);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to update payment.', [
                'message' => $e->getMessage(),
                'data' => $data,
            ]);
            return false;
        }
    }
}
