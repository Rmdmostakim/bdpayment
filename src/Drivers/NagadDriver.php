<?php

/**
 * NagadDriver
 *
 * Handles Nagad payment gateway integration: initialization, execution, verification, encryption, and logging.
 *
 * @package RmdMostakim\BdPayment\Drivers
 */

namespace RmdMostakim\BdPayment\Drivers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RmdMostakim\BdPayment\Contracts\NagadPaymentDriverInterface;
use RmdMostakim\BdPayment\Traits\InteractsWithPayments;

/**
 * Class NagadDriver
 *
 * @method array initializePayment(string $tranId)
 * @method array executePayment(array $payload)
 * @method array verifyPayment(string $paymentId)
 * @method string getPublicKey()
 * @method string getPrivateKey()
 * @method string encryptDataWithPublicKey(string $data)
 * @method string decryptDataWithPrivateKey(string $cryptText)
 * @method string signatureGenerate(string $data)
 * @method string generateRandomString(int $length = 40)
 */
class NagadDriver implements NagadPaymentDriverInterface
{
    use InteractsWithPayments;

    /**
     * Generate a random string for challenge.
     * @param int $length
     * @return string
     */
    public function generateRandomString(int $length = 40): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Get Nagad public key from storage.
     * @return string
     */
    public function getPublicKey(): string
    {
        $path = config('bdpayment.drivers.nagad.public_key');
        $publicKeyContent = Storage::disk('public')->get($path);

        return "-----BEGIN PUBLIC KEY-----\n" . trim($publicKeyContent) . "\n-----END PUBLIC KEY-----";
    }

    /**
     * Get Nagad private key from storage.
     * @return string
     */
    public function getPrivateKey(): string
    {
        $path = config('bdpayment.drivers.nagad.private_key');
        $privateContent  = Storage::disk('public')->get($path);
        return "-----BEGIN RSA PRIVATE KEY-----\n" . trim($privateContent) . "\n-----END RSA PRIVATE KEY-----";
    }

    /**
     * Encrypt data using Nagad public key.
     * @param string $data
     * @return string
     */
    public function encryptDataWithPublicKey(string $data): string
    {
        openssl_public_encrypt($data, $cryptText, openssl_get_publickey($this->getPublicKey()));
        return base64_encode($cryptText);
    }

    /**
     * Decrypt data using Nagad private key.
     * @param string $cryptText
     * @return string
     */
    public function decryptDataWithPrivateKey(string $cryptText): string
    {
        openssl_private_decrypt(base64_decode($cryptText), $plainText, $this->getPrivateKey());
        return $plainText;
    }

    /**
     * Generate signature using Nagad private key.
     * @param string $data
     * @return string
     */
    public function signatureGenerate(string $data): string
    {
        openssl_sign($data, $signature, $this->getPrivateKey(), OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * Send a POST request to Nagad API with standard headers.
     * @param string $url
     * @param array $data
     * @return array
     */
    protected function HttpPostMethod($url, $data)
    {
        Log::info("Sending POST request to Nagad API", [
            'url' => $url,
            'payload' => $data
        ]);

        return retry(3, function () use ($url, $data) {
            $response = Http::withHeaders([
                'Content-Type'     => 'application/json',
                'X-KM-Api-Version' => 'v-0.2.0',
                'X-KM-IP-V4'       => request()->ip(),
                'X-KM-Client-Type' => 'PC_WEB',
            ])->withoutVerifying()->post($url, $data);

            Log::info("Received response from Nagad API", [
                'endpoint' => $url,
                'status' => $response->status(),
                'headers'  => $response->headers(),
                'response' => $response->json(),
            ]);

            return $response->json();
        }, 500);
    }

    /**
     * Send a GET request to Nagad API with standard headers.
     * @param string $url
     * @param array $query
     * @return array
     */
    protected function HttpGetMethod($url, $query = [])
    {
        $headers = [
            'Content-Type'     => 'application/json',
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4'       => request()->ip(),
            'X-KM-Client-Type' => 'PC_WEB',
        ];

        Log::info("Sending GET request to Nagad API", [
            'url' => $url,
            'query' => $query,
            'headers' => $headers,
        ]);

        return retry(3, function () use ($url, $query, $headers) {
            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->get($url, $query);

            Log::info("Received GET response from Nagad API", [
                'endpoint' => $url,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'response' => $response->json(),
            ]);

            return $response->json();
        }, 500);
    }

    /**
     * Initialize a Nagad payment.
     * @param string $tranId
     * @return array
     */
    public function initializePayment(string $tranId): array
    {
        try {
            date_default_timezone_set('Asia/Dhaka');

            $MerchantID = config("bdpayment.drivers.nagad.merchant_id");
            $DateTime   = date('YmdHis');
            $random     = $this->generateRandomString();
            $baseUrl    = config("bdpayment.drivers.nagad.base_url");
            $mode = config("bdpayment.drivers.nagad.mode", 'sandbox');

            $initUrl = $mode == 'production' ? "{$baseUrl}/api/dfs/check-out/initialize/{$MerchantID}/{$tranId}" : "{$baseUrl}/remote-payment-gateway-1.0/api/dfs/check-out/initialize/{$MerchantID}/{$tranId}";

            $sensitiveData = [
                'merchantId' => $MerchantID,
                'datetime'   => $DateTime,
                'orderId'    => $tranId,
                'challenge'  => $random
            ];

            $postData = [
                'dateTime'      => $DateTime,
                'sensitiveData' => $this->EncryptDataWithPublicKey(json_encode($sensitiveData)),
                'signature'     => $this->SignatureGenerate(json_encode($sensitiveData))
            ];

            Log::info("Initializing Nagad payment", [
                'transaction_id' => $tranId,
                'request' => $postData
            ]);

            $initResponse = $this->HttpPostMethod($initUrl, $postData);

            Log::info("Nagad initialization response", [
                'transaction_id' => $tranId,
                'response' => $initResponse
            ]);

            if (empty($initResponse['sensitiveData']) || empty($initResponse['signature'])) {
                throw new Exception("Invalid response from Nagad initialization.");
            }

            $plainResponse = json_decode($this->DecryptDataWithPrivateKey($initResponse['sensitiveData']), true);

            Log::info("Decrypted Nagad initialization response", [
                'transaction_id' => $tranId,
                'plain_response' => $plainResponse
            ]);

            if (empty($plainResponse['paymentReferenceId']) || empty($plainResponse['challenge'])) {
                throw new Exception("Invalid plain response: " . json_encode($plainResponse));
            }

            return [
                'paymentReferenceId' => $plainResponse['paymentReferenceId'],
                'challenge'         => $plainResponse['challenge']
            ];
        } catch (Exception $e) {
            Log::error("Nagad initializePayment Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Execute a Nagad payment.
     * @param array $payload
     * @return array
     */
    public function executePayment(array $payload): array
    {
        try {
            $MerchantID = config("bdpayment.drivers.nagad.merchant_id");
            $baseUrl    = config("bdpayment.drivers.nagad.base_url");
            $callback   = config("bdpayment.drivers.nagad.callback_url");
            $mode = config("bdpayment.drivers.nagad.mode", 'sandbox');
            $filtered = $this->filterNagadPayload($payload);
            $invoice  = $this->storePayment($filtered, 'nagad');
            $sensitiveDataOrder = [
                'merchantId'   => $MerchantID,
                'orderId'      => $invoice,
                'paymentReferenceId' => $filtered['transaction_id'],
                'currencyCode' => '050',
                'amount'       => $payload['amount'],
                'challenge'    => $payload['challenge']
            ];

            $logo = url('brand.svg');
            $merchantAdditionalInfo = [
                'serviceName'           => config('app.name', 'MyApp'),
                'serviceLogoURL'        => $logo,
                'additionalFieldNameEN' => 'Type',
                'additionalFieldNameBN' => 'টাইপ',
                'additionalFieldValue'  => 'Payment'
            ];

            $orderPostData = [
                'sensitiveData'       => $this->EncryptDataWithPublicKey(json_encode($sensitiveDataOrder)),
                'signature'           => $this->SignatureGenerate(json_encode($sensitiveDataOrder)),
                'merchantCallbackURL' => $callback,
                'additionalMerchantInfo' => $merchantAdditionalInfo
            ];

            $orderSubmitUrl = $mode == 'production' ? "$baseUrl/api/dfs/check-out/complete/{$filtered['transaction_id']}" : "$baseUrl/remote-payment-gateway-1.0/api/dfs/check-out/complete/{$filtered['transaction_id']}";

            Log::info("Executing Nagad payment", [
                'transaction_id' => $payload['transaction_id'],
                'request' => $orderPostData
            ]);

            $orderResponse = $this->HttpPostMethod($orderSubmitUrl, $orderPostData);

            Log::info("Nagad execute payment response", [
                'transaction_id' => $payload['transaction_id'],
                'response' => $orderResponse
            ]);

            if (!empty($orderResponse['status']) && $orderResponse['status'] === 'Success') {
                return [
                    'status' => 'success',
                    'redirectUrl' => $orderResponse['callBackUrl'],
                ];
            }

            throw new Exception("Order submission failed: " . json_encode($orderResponse));
        } catch (Exception $e) {
            Log::error("Nagad executePayment Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    /**
     * Verify a Nagad payment.
     * @param string $paymentId
     * @return array
     */
    public function verifyPayment(string $paymentId): array
    {
        try {
            $MerchantID = config("bdpayment.drivers.nagad.merchant_id");
            $baseUrl    = config("bdpayment.drivers.nagad.base_url");
            $mode = config("bdpayment.drivers.nagad.mode", 'sandbox');

            $verifyUrl = $mode == 'production' ? "{$baseUrl}/api/dfs/verify/payment/{$paymentId}" : "{$baseUrl}/remote-payment-gateway-1.0/api/dfs/verify/payment/{$paymentId}";

            $sensitiveData = [
                'merchantId' => $MerchantID,
                'paymentReferenceId' => $paymentId,
                'challenge'  => $this->generateRandomString()
            ];

            $postData = [
                'sensitiveData' => $this->EncryptDataWithPublicKey(json_encode($sensitiveData)),
                'signature'     => $this->SignatureGenerate(json_encode($sensitiveData))
            ];

            Log::info("Verifying Nagad payment", [
                'payment_reference_id' => $paymentId,
                'request' => $postData
            ]);

            return $verifyResponse = $this->HttpGetMethod($verifyUrl);

            Log::info("Nagad verify payment response", [
                'payment_reference_id' => $paymentId,
                'response' => $verifyResponse
            ]);

            if (empty($verifyResponse['sensitiveData']) || empty($verifyResponse['signature'])) {
                throw new Exception("Invalid response from Nagad verification.");
            }

            $plainResponse = json_decode($this->DecryptDataWithPrivateKey($verifyResponse['sensitiveData']), true);

            Log::info("Decrypted Nagad verification response", [
                'payment_reference_id' => $paymentId,
                'plain_response' => $plainResponse
            ]);

            return $plainResponse;
        } catch (Exception $e) {
            Log::error("Nagad verifyPayment Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payment_id' => $paymentId
            ]);
            throw $e;
        }
    }
}
