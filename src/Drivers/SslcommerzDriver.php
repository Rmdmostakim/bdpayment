<?php

/**
 * SslcommerzDriver
 *
 * Handles SSLCommerz payment gateway integration: payment creation, verification, and logging.
 *
 * @package RmdMostakim\BdPayment\Drivers
 */

namespace RmdMostakim\BdPayment\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RmdMostakim\BdPayment\Contracts\SslcommerzPaymentDriverInterface;
use RmdMostakim\BdPayment\Traits\InteractsWithPayments;

class SslcommerzDriver implements SslcommerzPaymentDriverInterface
{
    use InteractsWithPayments;

    protected string $baseUrl;
    protected string $storeId;
    protected string $storePassword;
    protected array $headers;
    protected string $mode;
    protected array $defaultCustomerInfo = [];

    public function __construct()
    {
        $this->baseUrl = rtrim(config('bdpayment.drivers.sslcommerz.base_url'), '/');
        $this->storeId = config('bdpayment.drivers.sslcommerz.store_id');
        $this->storePassword = config('bdpayment.drivers.sslcommerz.store_password');
        $this->headers = [
            'Content-Type' => 'application/json',
        ];
        $this->mode = config('bdpayment.drivers.sslcommerz.mode', 'sandbox');
    }
    /**
     * Create a new SSLCommerz payment session.
     * @param array $data
     * @return array|null
     */
    public function createPayment(array $data): array|null
    {
        try {
            $filtered = $this->filterSslcommerzPayload($data);
            $invoice  = $this->storePayment($filtered, 'sslcommerz');
            $payload  = $this->makeSslcommerzPayload($filtered, $invoice);

            Log::info('SSLCommerz create payment request', [
                'endpoint' => $this->baseUrl . '/gwprocess/v4/api.php',
                'headers' => $this->headers,
                'payload' => $payload,
            ]);

            // Use Laravel HTTP client with retry (3 times)
            $endpoint = $this->baseUrl . '/gwprocess/v4/api.php';
            $response = Http::withHeaders($this->headers)
                ->retry(3, 200)
                ->asForm()
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $sslcz = $response->json();
                Log::info($sslcz);

                if (isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != "") {
                    return [
                        'status' => 'success',
                        'data' => $sslcz['GatewayPageURL'],
                        'logo' => $sslcz['storeLogo'] ?? null
                    ];
                } else {
                    return [
                        'status' => 'fail',
                        'data' => null,
                        'message' => "JSON Data parsing error!"
                    ];
                }
            } else {
                Log::error('FAILED TO CONNECT WITH SSLCOMMERZ API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'status' => 'fail',
                    'data' => null,
                    'message' => 'FAILED TO CONNECT WITH SSLCOMMERZ API'
                ];
            }
        } catch (\Exception $e) {
            Log::error('SSLCommerz create payment failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify an SSLCommerz payment.
     * @param string $tranId
     * @return array|null
     */
    public function verifyPayment(string $tranId): array|null
    {
        try {
            $payload = [
                'val_id' => $tranId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
            ];

            Log::info('SSLCommerz verify payment request', [
                'endpoint' => $this->baseUrl . '/validator/api/validationserverAPI.php',
                'headers' => $this->headers,
                'payload' => $payload,
            ]);

            // Use Laravel HTTP client with retry (3 times)
            $endpoint = $this->baseUrl . '/validator/api/validationserverAPI.php';
            $response = Http::withHeaders($this->headers)
                ->retry(3, 200)
                ->get($endpoint, $payload);

            if ($response->successful()) {
                Log::info('SSLCommerz verify payment response', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->json(),
                ]);
                return $response->json();
            } else {
                Log::error('SSLCommerz verify payment failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('SSLCommerz verify payment failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
