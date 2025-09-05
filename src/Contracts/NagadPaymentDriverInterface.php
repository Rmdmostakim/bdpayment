<?php

namespace RmdMostakim\BdPayment\Contracts;

interface NagadPaymentDriverInterface
{
    /**
     * Generate a random string
     */
    public function generateRandomString(int $length = 40): string;

    /**
     * Get the public key for encryption
     */
    public function getPublicKey(): string;

    /**
     * Get the private key for decryption
     */
    public function getPrivateKey(): string;

    /**
     * Encrypt data using public key
     */
    public function encryptDataWithPublicKey(string $data): string;

    /**
     * Decrypt data using private key
     */
    public function decryptDataWithPrivateKey(string $cryptText): string;

    /**
     * Generate signature for data
     */
    public function signatureGenerate(string $data): string;


    /**
     * Initialize payment with Nagad
     *
     * @param string $tranId The transaction ID
     * @return array Contains paymentReferenceId and challenge
     * @throws Exception
     */
    public function initializePayment(string $tranId): array;

    /**
     * Execute the payment transaction
     *
     * @param array $payload Payment data including tran_id, amount, challenge, etc.
     * @return array Contains status and redirectUrl
     * @throws Exception
     */
    public function executePayment(array $payload): array;
}
