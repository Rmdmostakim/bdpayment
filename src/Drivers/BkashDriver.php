<?php

/**
 * BkashDriver
 *
 * Handles Bkash payment gateway integration: token management, payment creation, execution, verification, and logging.
 *
 * @package RmdMostakim\BdPayment\Drivers
 */

namespace RmdMostakim\BdPayment\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RmdMostakim\BdPayment\Contracts\BkashPaymentDriverInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use RmdMostakim\BdPayment\Traits\InteractsWithPayments;

/**
 * Class BkashDriver
 *
 * @method string|null token()
 * @method array|null createPayment(array $data)
 * @method array|null executePayment(string $paymentId)
 * @method array|null verifyPayment(string $paymentId)
 * @method bool cancelPayment(string $paymentId)
 */
class BkashDriver implements BkashPaymentDriverInterface
{
    use InteractsWithPayments;

    /** @var string Bkash gateway mode (sandbox/production) */
    protected string $mode;
    /** @var string Merchant number for Bkash */
    protected string $merchant_number;
    /** @var string Bkash API base URL */
    protected string $baseUrl;
    /** @var array Bkash API headers */
    protected array $headers;

    /**
     * BkashDriver constructor.
     * Initializes config values and headers.
     */
    public function __construct()
    {
        $this->mode = config('bdpayment.drivers.bkash.mode', 'sandbox');
        $this->merchant_number = config('bdpayment.drivers.bkash.merchant_number', '');
        $this->baseUrl = rtrim(config('bdpayment.drivers.bkash.base_url'), '/');
        $this->headers = [
            'Content-Type' => 'application/json',
            'username' => config('bdpayment.drivers.bkash.username'),
            'password' => config('bdpayment.drivers.bkash.password'),
            'x-app-key' => config('bdpayment.drivers.bkash.app_key'),
        ];
    }

    /**
     * Get or generate a Bkash API token.
     * @return string|null
     */
    public function token(): ?string
    {
        return Cache::remember('bkash_token', now()->addHour(), function () {
            $endpoint = $this->buildEndpoint('token');

            $body = [
                'app_key'    => config('bdpayment.drivers.bkash.app_key'),
                'app_secret' => config('bdpayment.drivers.bkash.app_secret'),
            ];

            Log::info('Bkash token request', [
                'endpoint' => $endpoint,
                'headers' => $this->headers,
                'payload' => $body,
            ]);

            try {
                $response = Http::withHeaders($this->headers)
                    ->retry(3, 100)
                    ->post($endpoint, $body)
                    ->throw();

                Log::info('Bkash token response', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->json(),
                ]);

                return $response->json('id_token');
            } catch (RequestException $e) {
                Log::error('Bkash token request failed', [
                    'error'    => $e->getMessage(),
                    'response' => optional($e->response)->json(),
                ]);
                return null;
            }
        });
    }

    /**
     * Create a new Bkash payment.
     * @param array $data
     * @return array|null
     */
    public function createPayment(array $data): array|null
    {
        try {
            $filtered = $this->filterBkashPayload($data);
            $invoice  = $this->storePayment($filtered, 'bkash');
            $payload  = $this->makeBkashPayload($filtered, $invoice);

            Log::info('Bkash create payment request', [
                'endpoint' => $this->buildEndpoint('create'),
                'headers' => $this->headers,
                'payload' => $payload,
            ]);

            $response = Http::withToken($this->token())
                ->withHeaders($this->headers)
                ->retry(3, 100)
                ->post($this->buildEndpoint('create'), $payload)
                ->throw();

            Log::info('Bkash create payment response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json(),
            ]);

            $response = $response->json();
            if (isset($response['paymentID'])) {
                $this->updatePayment($invoice, ['transaction_id' => $response['paymentID']]);
            }

            return $response;
        } catch (RequestException $e) {
            Log::error('Bkash create payment failed', [
                'error' => $e->getMessage(),
                'response' => $e->response?->json(),
            ]);
            return null;
        }
    }

    /**
     * Execute a Bkash payment by payment ID.
     * @param string $paymentId
     * @return array|null
     */
    public function executePayment(string $paymentId): array|null
    {
        try {
            $endpoint = $this->mode === 'production'
                ? $this->buildEndpoint('execute') . '/' . $paymentId
                : $this->buildEndpoint('execute');

            $payload = ['paymentID' => $paymentId];

            Log::info('Bkash execute payment request', [
                'endpoint' => $endpoint,
                'headers' => $this->headers,
                'payload' => $payload,
            ]);

            $response = Http::withToken($this->token())
                ->withHeaders($this->headers)
                ->retry(3, 100)
                ->post($endpoint, $payload)
                ->throw();

            Log::info('Bkash execute payment response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json(),
            ]);

            return $response->json();
        } catch (RequestException $e) {
            Log::error("Bkash execute payment failed for ID: {$paymentId}", [
                'error' => $e->getMessage(),
                'response' => $e->response?->json(),
            ]);
            return null;
        }
    }

    /**
     * Verify a Bkash payment by payment ID.
     * @param string $paymentId
     * @return array|null
     */
    public function verifyPayment(string $paymentId): array|null
    {
        try {
            $endpoint = $this->mode === 'production'
                ? $this->buildEndpoint('query') . '/' . $paymentId
                : $this->buildEndpoint('query');

            $payload = ['paymentID' => $paymentId];

            Log::info('Bkash verify payment request', [
                'endpoint' => $endpoint,
                'headers' => $this->headers,
                'payload' => $payload,
            ]);

            $response = Http::withToken($this->token())
                ->withHeaders($this->headers)
                ->retry(3, 100)
                ->post($endpoint, $payload)
                ->throw();

            Log::info('Bkash verify payment response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json(),
            ]);

            return $response->json();
        } catch (RequestException $e) {
            Log::error("Bkash payment verification failed for ID: {$paymentId}", [
                'error' => $e->getMessage(),
                'response' => $e->response?->json(),
            ]);
            return null;
        }
    }

    /**
     * Cancel a Bkash payment by payment ID.
     * @param string $paymentId
     * @return bool
     */
    public function cancelPayment(string $paymentId): bool
    {
        return true;
    }

    /**
     * Build the full endpoint URL based on mode and method.
     * @param string $method
     * @return string
     */
    protected function buildEndpoint(string $method): string
    {
        $url = [
            'token' => $this->mode == 'production' ? "{$this->baseUrl}/checkout/token/grant" : "{$this->baseUrl}/tokenized/checkout/token/grant",
            'create' => $this->mode == 'production' ? "{$this->baseUrl}/checkout/payment/create" : "{$this->baseUrl}/tokenized/checkout/create",
            'execute' => $this->mode == 'production' ? "{$this->baseUrl}/checkout/payment/execute" : "{$this->baseUrl}/tokenized/checkout/execute",
            'query' => $this->mode == 'production' ? "{$this->baseUrl}/checkout/payment/status" : "{$this->baseUrl}/tokenized/checkout/payment/status",
        ];
        return $url[$method] ?? "";
    }
}
