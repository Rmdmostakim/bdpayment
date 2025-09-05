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

use Illuminate\Http\JsonResponse;
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
    /***
     * Filter and validate SSLCommerz payment payload
     */
    protected function filterSslcommerzPayload(array $payload): array
    {
        $requiredKeys = ["amount"];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                Log::error("SSLCommerz create payment failed: missing required key '$key'");
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

        if (array_key_exists("category", $payload)) {
            $filtered["category"] = $payload["category"];
        }

        if (array_key_exists("product_profile", $payload)) {
            $filtered["product_profile"] = $payload["product_profile"];
        }

        if (array_key_exists("product_name", $payload)) {
            $filtered["product_name"] = $payload["product_name"];
        }

        if (array_key_exists("user_name", $payload)) {
            $filtered["user_name"] = $payload["user_name"];
        }

        if (array_key_exists("user_email", $payload)) {
            $filtered["user_email"] = $payload["user_email"];
        }

        if (array_key_exists("user_phone", $payload)) {
            $filtered["user_phone"] = $payload["user_phone"];
        }

        if (array_key_exists("address", $payload)) {
            $filtered["address"] = $payload["address"];
        }

        if (array_key_exists("city", $payload)) {
            $filtered["city"] = $payload["city"];
        }
        if (array_key_exists("state", $payload)) {
            $filtered["state"] = $payload["state"];
        }
        if (array_key_exists("country", $payload)) {
            $filtered["country"] = $payload["country"];
        }
        if (array_key_exists("postal_code", $payload)) {
            $filtered["postal_code"] = $payload["postal_code"];
        }
        return $filtered;
    }
    /**
     * Build the payload for SSLCommerz API
     */
    protected function makeSslcommerzPayload(array $filtered, string $invoice): array
    {
        return [
            // Store credentials
            'store_id'      => config('bdpayment.drivers.sslcommerz.store_id'),
            'store_passwd'  => config('bdpayment.drivers.sslcommerz.store_password'),

            // Transaction details
            'total_amount'  => (string) ($filtered['amount'] ?? '0.00'),
            'currency'      => 'BDT',
            'tran_id'       => $invoice,

            // Callback URLs
            'success_url'   => config('bdpayment.drivers.sslcommerz.callback_url'),
            'fail_url'      => config('bdpayment.drivers.sslcommerz.callback_url'),
            'cancel_url'    => config('bdpayment.drivers.sslcommerz.callback_url'),

            // Product information
            'product_category' => $filtered['category']         ?? 'None',
            'product_profile'  => $filtered['product_profile']  ?? 'non-physical-goods',
            'product_name'     => $filtered['product_name']     ?? 'Service',

            // EMI information
            'emi_option'    => "0",

            // Customer information
            'cus_name'      => $filtered['user_name']  ?? config('app.name'),
            'cus_email'     => $filtered['user_email'] ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_email', 'test@test.com'),
            'cus_add1'      => $filtered['address']    ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_add1', 'Dhaka'),
            'cus_city'      => $filtered['city']       ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_city', 'Dhaka'),
            'cus_state'     => $filtered['state']      ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_state', ''),
            'cus_postcode'  => $filtered['postal_code'] ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_postcode', ''),
            'cus_country'   => $filtered['country']    ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_country', 'Bangladesh'),
            'cus_phone'     => $filtered['user_phone'] ?? config('bdpayment.drivers.sslcommerz.default_customer_info.cus_phone', '01711111111'),

            // Shipment information
            'shipping_method' => 'NO',
        ];
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
            // Only keep the fillable fields from the model
            // $fillable = (new Bdpayment())->getFillable();
            // $filteredPayload = array_intersect_key($payload, array_flip($fillable));
            $result = Bdpayment::create($payload);

            return $result->invoice ?? null;
        } catch (\Throwable $e) {
            Log::error('Failed to store payment.', [
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
    /**
     * Paginated Response
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @param string $message
     * @return JsonResponse
     */
    public function paginatedResponse($paginator, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage()
            ]
        ]);
    }
    /**
     * Success Response
     *
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public function successResponse(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Error Response
     *
     * @param string $message
     * @param int $status
     * @param mixed $errors
     * @return JsonResponse
     */
    public function errorResponse(string $message = 'Error', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
    /**
     * Custom Response
     *
     * @param bool $status
     * @param string $message
     * @param mixed $data
     * @param int $code
     * @return JsonResponse
     */
    public function customResponse(bool $status, string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], $code);
    }
}
