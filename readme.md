# BdPayment Laravel Package

A Laravel package for integrating Bangladesh payment gateways (Bkash, Nagad) with unified API endpoints, logging, and frontend callback support.

## Features

- Bkash and Nagad payment gateway integration
- Unified API for payment creation, execution, and callback
- Configurable via `.env` and `config/bdpayment.php`
- Frontend callback URL support
- Extensive logging and error handling

## Installation

```bash
composer require rmdmostakim/bdpayment
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --provider="RmdMostakim\BdPayment\BdPaymentServiceProvider"
```

Set the following variables in your `.env`:

```env
APP_URL=http://localhost
FRONTEND_PAYMENT_SUCCESS_URL=http://localhost:3000/payment

BKASH_GATEWAY_MODE=sandbox
BKASH_BASE_URL="https://tokenized.sandbox.bka.sh/v1.2.0-beta"
BKASH_USERNAME="sandboxTokenizedUser02"
BKASH_PASSWORD="sandboxTokenizedUser02@12345"
BKASH_APP_KEY="your_app_key"
BKASH_APP_SECRET="your_app_secret"
BKASH_CALLBACK_URL="${APP_URL}/api/gateway/bkash/callback"

NAGAD_PAYMENT_MODE=sandbox
NAGAD_BASE_URL="http://sandbox.mynagad.com:10080"
NAGAD_MERCHANT_ID="your_merchant_id"
NAGAD_PUBLIC_KEY="keys/nagad_public_key.pem"
NAGAD_PRIVATE_KEY="keys/nagad_private_key.pem"
NAGAD_CALLBACK_URL="${APP_URL}/api/gateway/nagad/callback"
```

Edit `config/bdpayment.php` as needed. Default callback URLs use your `APP_URL`.

## Usage

### API Endpoints

#### Bkash

- **Create Payment:**  
  `POST /api/gateway/bkash/create`  
  **Payload:**  
  ```json
  {
    "amount": 100,
    "invoice": "INV123", // optional
    "user_id": 1,        // optional
    "product_id": 5      // optional
  }
  ```

- **Execute Payment:**  
  `POST /api/gateway/bkash/execute`  
  **Payload:**  
  ```json
  {
    "paymentID": "bkash_payment_id"
  }
  ```

- **Callback:**  
  `GET /api/gateway/bkash/callback?paymentID=...`  
  Redirects to frontend callback URL with status and message.

#### Nagad

- **Create Payment:**  
  `POST /api/gateway/nagad/create`  
  **Payload:**  
  ```json
  {
    "amount": 100,
    "invoice": "INV123", // optional
    "user_id": 1,        // optional
    "product_id": 5      // optional
  }
  ```

- **Callback:**  
  `GET /api/gateway/nagad/callback?payment_ref_id=...&order_id=...`  
  Redirects to frontend callback URL with status and message.

## Logging

All requests, responses, and errors are logged using Laravel's logging system for easy debugging and traceability.

## Frontend Callback

After payment, users are redirected to the configured frontend callback URL with query parameters:

- `invoice` (invoice)
- `status` (`success` or `failed`)
- `message` (status message)

## Using Facades

You can use the `PaymentManager` facade for direct access to gateway methods in your code:

```php
use RmdMostakim\BdPayment\Facades\PaymentManager;

// Create a Bkash payment
$response = PaymentManager::bkash()->createPayment([
    'amount' => 100,
    'invoice' => 'INV123',
    'user_id' => 1,
    'product_id' => 5
]);

// Execute a Bkash payment
$executeResponse = PaymentManager::bkash()->executePayment('bkash_payment_id');

// Verify a Bkash payment
$verifyResponse = PaymentManager::bkash()->verifyPayment('bkash_payment_id');

// Create a Nagad payment
$init = PaymentManager::nagad()->initializePayment('INV123');
$payload = [
    'user_id' => 1,
    'invoice' => 'INV123',
    'amount' => 100,
    'product_id' => 5,
    'transaction_id' => $init['paymentReferenceId'],
    'challenge' => $init['challenge']
];
$nagadResponse = PaymentManager::nagad()->executePayment($payload);

// Verify a Nagad payment
$verifyNagad = PaymentManager::nagad()->verifyPayment($init['paymentReferenceId']);
```

## Extending

You can add more gateways by extending the drivers in `packages/rmdmostakim/bdpayment/src/Drivers` and updating the config.

## License

MIT
