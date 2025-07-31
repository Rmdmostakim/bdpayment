<?php

namespace RmdMostakim\BdPayment\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RmdMostakim\BdPayment\Contracts\BkashPaymentDriverInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use RmdMostakim\BdPayment\Traits\InteractsWithPayments;

class BkashDriver implements BkashPaymentDriverInterface
{
    use InteractsWithPayments;

    protected string $mode;
    protected string $merchant_number;
    protected string $baseUrl;
    protected array $headers;

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

    public function token(): ?string
    {
        return Cache::remember('bkash_token', now()->addHour(), function () {
            $endpoint = $this->buildEndpoint('token');

            $body = [
                'app_key'    => config('bdpayment.drivers.bkash.app_key'),
                'app_secret' => config('bdpayment.drivers.bkash.app_secret'),
            ];

            try {
                $response = Http::withHeaders($this->headers)
                    ->post($endpoint, $body)
                    ->throw();

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

    public function createPayment(array $data): array|null
    {
        try {
            $filtered = $this->filterPayload($data);
            $invoice  = $this->storePayment($filtered, 'bkash');
            $payload  = $this->makeBkashPayload($filtered, $invoice);

            $response = Http::withToken($this->token())
                ->withHeaders($this->headers)
                ->post($this->buildEndpoint('create'), $payload)
                ->throw();

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

    public function executePayment(string $paymentId): array|null
    {
        try {
            $endpoint = $this->mode === 'production'
                ? $this->buildEndpoint('execute') . '/' . $paymentId
                : $this->buildEndpoint('execute');

            $payload = $this->mode === 'production'
                ? []
                : ['paymentID' => $paymentId];

            $response = Http::withToken($this->token())
                ->withHeaders($this->headers)
                ->post($endpoint, $payload)
                ->throw();

            return $response->json();
        } catch (RequestException $e) {
            Log::error("Bkash execute payment failed for ID: {$paymentId}", [
                'error' => $e->getMessage(),
                'response' => $e->response?->json(),
            ]);
            return null;
        }
    }

    public function verifyPayment(string $paymentId): array|null
    {
        try {
            $endpoint = $this->mode === 'production'
                ? $this->buildEndpoint('query') . '/' . $paymentId
                : $this->buildEndpoint('query');

            $payload = $this->mode === 'production'
                ? []
                : ['paymentID' => $paymentId];

            $response = Http::withToken($this->token())
                ->withHeaders($this->headers)
                ->post($endpoint, $payload)
                ->throw();
            return $response->json();
        } catch (RequestException $e) {
            Log::error("Bkash payment verification failed for ID: {$paymentId}", [
                'error' => $e->getMessage(),
                'response' => $e->response?->json(),
            ]);
            return null;
        }
    }


    public function cancelPayment(string $paymentId): bool
    {
        return true;
    }

    /**
     * Build the full endpoint URL based on mode.
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
