<?php

/**
 * Interface BkashPaymentDriverInterface
 *
 * Contract for Bkash payment gateway driver.
 * Defines required methods for token management, payment creation, execution, verification, and cancellation.
 *
 * @package RmdMostakim\BdPayment\Contracts
 */

namespace RmdMostakim\BdPayment\Contracts;

interface BkashPaymentDriverInterface
{
    /**
     * Get or generate a Bkash API token.
     * @return string|null
     */
    public function token(): string | null;

    /**
     * Create a new Bkash payment.
     * @param array $data
     * @return array|null
     */
    public function createPayment(array $data): array | null;

    /**
     * Execute a Bkash payment by payment ID.
     * @param string $paymentId
     * @return array|null
     */
    public function executePayment(string $paymentId): array | null;

    /**
     * Verify a Bkash payment by payment ID.
     * @param string $paymentId
     * @return array|null
     */
    public function verifyPayment(string $paymentId): array | null;

    /**
     * Cancel a Bkash payment by payment ID.
     * @param string $paymentId
     * @return bool
     */
    public function cancelPayment(string $paymentId): bool;
}
