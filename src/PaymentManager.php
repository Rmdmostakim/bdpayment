<?php

/**
 * PaymentManager
 *
 * Provides access to payment gateway drivers (Bkash, Nagad, etc).
 * Use this manager to interact with supported gateways in a unified way.
 *
 * @package RmdMostakim\BdPayment
 */

namespace RmdMostakim\BdPayment;

use Illuminate\Http\JsonResponse;
use RmdMostakim\BdPayment\Drivers\BkashDriver;
use RmdMostakim\BdPayment\Drivers\NagadDriver;
use RmdMostakim\BdPayment\Drivers\SslcommerzDriver;
use RmdMostakim\BdPayment\Models\Bdpayment;
use RmdMostakim\BdPayment\Traits\InteractsWithPayments;

/**
 * Class PaymentManager
 *
 * Central manager for payment gateway drivers and payment queries.
 *
 * @method BkashDriver bkash()
 * @method NagadDriver nagad()
 * @method SslcommerzDriver sslcommerz()
 */
class PaymentManager
{
    use InteractsWithPayments;

    /**
     * Get the Bkash payment driver.
     * @return BkashDriver
     */
    public function bkash(): BkashDriver
    {
        return new BkashDriver();
    }

    /**
     * Get the Nagad payment driver.
     * @return NagadDriver
     */
    public function nagad(): NagadDriver
    {
        return new NagadDriver();
    }

    /**
     * Get the SSLCommerz payment driver.
     * @return SslcommerzDriver
     */
    public function sslcommerz(): SslcommerzDriver
    {
        return new SslcommerzDriver();
    }

    /**
     * Get a paginated list of payments with optional filters.
     *
     * @param array $data Filter and pagination options:
     *                    - status, mode, user_id, min_amount, max_amount, from, to, sortBy, sortDir, perPage
     * @return JsonResponse Paginated payments response
     */
    public function all(array $data = []): JsonResponse
    {
        // Extract filters from input array
        $filters = [
            'status'     => $data['status']     ?? null,
            'mode'       => $data['mode']       ?? null,
            'user_id'    => $data['user_id']    ?? null,
            'min_amount' => $data['min_amount'] ?? null,
            'max_amount' => $data['max_amount'] ?? null,
            'from'       => $data['from']       ?? null,
            'to'         => $data['to']         ?? null,
        ];
        $sortBy  = $data['sortBy']  ?? 'created_at';
        $sortDir = $data['sortDir'] ?? 'desc';
        $perPage = isset($data['perPage']) ? (int)$data['perPage'] : 10;

        // Retrieve paginated payments using model method
        $payments = Bdpayment::getAll($filters, $sortBy, $sortDir, $perPage);

        // Return paginated response
        return $this->paginatedResponse($payments);
    }

    /**
     * Find a payment by its invoice number.
     *
     * @param string $invoice
     * @return JsonResponse
     */
    public function findByInvoice(string $invoice): JsonResponse
    {
        $payment = Bdpayment::findByInvoice($invoice);

        if (!$payment) {
            // Return not found response using customResponse helper
            return $this->customResponse(false, 'Payment not found', null, 404);
        }

        // Return success response with payment data
        return $this->successResponse($payment);
    }
}
