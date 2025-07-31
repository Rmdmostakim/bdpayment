<?php

namespace RmdMostakim\BdPayment\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use RmdMostakim\BdPayment\Models\Bdpayment;

trait InteractsWithPayments
{
    protected function filterPayload(array $payload): array
    {
        $requiredKeys = ['user_id', 'amount'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                Log::error("Bkash create payment failed: missing required key '$key'");
                throw ValidationException::withMessages([
                    $key => "The $key field is required."
                ]);
            }
        }

        $filtered = array_intersect_key($payload, array_flip($requiredKeys));

        if (array_key_exists("invoice", $payload)) {
            $filtered["invoice"] = $payload["invoice"];
        }

        return $filtered;
    }

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


    protected function makeBkashPayload(array $filtered, string $invoice): array
    {
        $isApi = Request::is('api/*');
        $mode = config('bdpayment.drivers.bkash.mode', 'sandbox');

        $payload = [
            'mode'                   => '0011',
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
}
