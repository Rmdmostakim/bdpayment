<?php

namespace RmdMostakim\BdPayment\Contracts;

interface BkashPaymentDriverInterface
{
    public function token(): string | null;
    public function createPayment(array $data): array | null;
    public function executePayment(string $paymentId): array | null;
    public function verifyPayment(string $paymentId): array | null;
    public function cancelPayment(string $paymentId): bool;
}
