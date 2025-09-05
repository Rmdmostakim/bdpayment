<?php

/**
 * Interface SslcommerzPaymentDriverInterface
 *
 * Contract for SSLCommerz payment gateway driver.
 * Defines required methods for payment creation and verification.
 *
 * @package RmdMostakim\BdPayment\Contracts
 */

namespace RmdMostakim\BdPayment\Contracts;

interface SslcommerzPaymentDriverInterface
{
    /**
     * Create a new SSLCommerz payment.
     * @param array $data
     * @return array|null
     */
    public function createPayment(array $data): array|null;

    /**
     * Verify an SSLCommerz payment by transaction ID.
     * @param string $tranId
     * @return array|null
     */
    public function verifyPayment(string $tranId): array|null;
}
