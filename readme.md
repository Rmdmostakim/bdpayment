# 💳 BDPayment - Laravel Integration for bKash & Nagad

A simple and extensible Laravel package for integrating **bKash** and **Nagad** (and other Bangladeshi) payment gateways.  
Developed and maintained by [Mostakim Rahman](https://github.com/Rmdmostakim).

---

## 🛠 Features

- ✅ bKash Token, Checkout, Execute & Verify APIs  
- 🚧 Nagad Integration (Coming Soon)  
- 📝 SSLCommerz, Rocket, City Bank, DBBL (Planned)  
- 🔒 Supports Laravel Sanctum & Passport  
- ⚙️ Works with Web, API & ReactJS Frontends  

---

## 📦 Installation

### 1. Install

```bash
composer require rmdmostakim/bdpayment
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="RmdMostakim\BdPayment\PaymentServiceProvider"
```

### 3. Run Migration

```bash
php artisan migrate
```

### 4. Environment Configuration

Add the following to your `.env`:

```env
BKASH_APP_KEY=
BKASH_APP_SECRET=
BKASH_USERNAME=
BKASH_PASSWORD=
BKASH_BASE_URL=
BKASH_CALLBACK_URL=
BKASH_MERCHANT_NUMBER=
BKASH_GATEWAY_MODE=sandbox
```

---

## ✅ Usage

### 📌 For bKash

#### Option 1: Dependency Injection

```php
use RmdMostakim\BdPayment\PaymentManager;

public function getToken(PaymentManager $gateway)
{
    return $gateway->bkash()->token();
}
```

#### Option 2: Facade

```php
use RmdMostakim\BdPayment\Facades\BdPayment;

public function getToken()
{
    return BdPayment::bkash()->token();
}
```

#### Available Methods

```php
BdPayment::bkash()->token();                      // Get access token
BdPayment::bkash()->createPayment([...]);         // Create a checkout session
BdPayment::bkash()->executePayment($tranId);      // Confirm the transaction
BdPayment::bkash()->verifyPayment($tranId);       // Verify payment status

```
### 📥 `createPayment()` Parameters

This method accepts an array with the following keys:

| Key       | Type   | Required | Description                                      |
|-----------|--------|----------|--------------------------------------------------|
| `user_id` | int    | ✅ Yes   | Authenticated user's ID                          |
| `amount`  | float  | ✅ Yes   | The payment amount (BDT)                         |
| `invoice` | string | ✳️ Optional | Custom invoice number. Auto-generated if omitted |

#### Example:

```php
$response = BdPayment::bkash()->createPayment([
    'user_id' => auth()->id(),
    'amount' => 150,
    // 'invoice' => 'INV-20250731-01' // Optional
]);

```
- 🧠 Tip: If you don’t pass invoice, it will automatically generate a unique one.

- 📌 For sandbox redirect to sandbox bKash's payment page. For production checkout through your own domain, see the section below for an example.

---

## 🧩 Example: Use in Laravel Web Project

```php
<div class="max-w-sm bg-white rounded-lg shadow-md p-6">
    <img
        class="rounded-md w-full h-48 object-cover mb-4"
        src="https://placehold.co/400x300"
        alt="Product Image"
    />
    <h2 class="text-2xl font-semibold mb-2">Awesome Product</h2>
    <p class="text-gray-600 mb-4">
        This is a description of the awesome product that you will love!
    </p>
    <p class="text-lg font-bold mb-4">$49.99</p>

    <!-- Buy Form -->
    <form id="buy-form" method="POST" action="{{ route("gateway.bkash.index") }}">
        @csrf
        <input type="hidden" name="amount" value="49.99" />

        <button
            type="submit"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-md transition-colors"
        >
            Buy Now
        </button>
    </form>
</div>
```
#### Card
![Product Card](https://raw.githubusercontent.com/Rmdmostakim/screens/main/bdpayment-1.png)
---
#### Payment Page
![Payment Page](https://raw.githubusercontent.com/Rmdmostakim/screens/main/bdpayment-2.png)
---
#### Transaction Success
![Payment Success](https://raw.githubusercontent.com/Rmdmostakim/screens/main/bdpayment-3.png)
---
#### Transaction Failed
![Payment Failed](https://raw.githubusercontent.com/Rmdmostakim/screens/main/bdpayment-4.png)
---

## 🧪 Example: Use in API with Sanctum/Passport

> Ensure Bearer token is sent in the `Authorization` header.

```php
<div class="max-w-sm bg-white rounded-lg shadow-md p-6 text-center">
        <img class="rounded-md w-full h-48 object-cover mb-4" src="https://placehold.co/400x300" alt="Product Image" />
        <h2 class="text-2xl font-semibold mb-2">Awesome Product</h2>
        <p class="text-gray-600 mb-4">This is a description of the awesome product that you will love!</p>
        <p class="text-lg font-bold mb-4">৳ <span id="amount">49.9</span></p>

        <button id="payBtn"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-md transition-colors">
            Buy Now
        </button>

        <button id="bKash_button" class="hidden"></button>

        <!-- Message Container -->
        <div id="message-container"
            class="hidden mt-6 px-4 py-3 rounded-md shadow-lg text-white text-base font-medium flex items-center justify-between transition duration-300">
            <span id="message-text"></span>
            <button type="button" id="close-message"
                class="ml-4 text-xl font-bold leading-none focus:outline-none hover:text-gray-300">&times;</button>
        </div>
    </div>

    <!-- Axios CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        $(document).ready(function () {
            const bkashMode = 'sandbox'; // or 'production' based on your config
            const baseUrl = "http://payment-gateway.test"; // Replace with your gateway base URL
            const token = "1|REIxipB582kMpqcQSy8J5iJaMeBoCF0g79NJcH1U6474785d"; // Replace with real token
            const payAmount = document.getElementById('amount').innerText;

            let paymentID = null;
            let tranId = "";

            // Axios global headers
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            axios.defaults.headers.common['Content-Type'] = 'application/json';

            const messageContainer = $('#message-container');
            const messageText = $('#message-text');
            let messageTimeout = null;

            function showMessage(message, type = 'error') {
                const typeClasses = {
                    error: 'bg-red-500',
                    success: 'bg-green-500',
                    info: 'bg-blue-500',
                };

                const bgClass = typeClasses[type] || 'bg-gray-600';

                if (messageTimeout) clearTimeout(messageTimeout);

                messageContainer
                    .removeClass('hidden bg-red-500 bg-green-500 bg-blue-500 bg-gray-600')
                    .addClass(bgClass)
                    .fadeIn();

                messageText.html(message);

                messageTimeout = setTimeout(() => redirectToHome(), 5000);
            }

            function clearMessage() {
                if (messageTimeout) clearTimeout(messageTimeout);
                messageTimeout = null;

                messageContainer.fadeOut(200, () => {
                    messageText.text('');
                    messageContainer
                        .removeClass('bg-red-500 bg-green-500 bg-blue-500 bg-gray-600')
                        .addClass('hidden');
                });
            }

            function redirectToHome() {
                window.location.href = "/";
            }

            $('#close-message').on('click', function () {
                clearMessage();
                redirectToHome();
            });

            function handleAxiosError(error, context = "Request") {
                const status = error.response?.status;
                const response = error.response?.data;
                let msg = "";

                if (status === 403) {
                    msg = `${context} failed: Unauthorized. <a href="/" class="underline text-white font-bold ml-2">Home</a>`;
                } else if (status === 422) {
                    msg = response?.errors
                        ? response.errors[Object.keys(response.errors)[0]][0]
                        : response?.message || "Unprocessable request.";
                } else if (status === 500) {
                    msg = `${context} failed: Internal Server Error.`;
                } else {
                    msg = `${context} failed: Unexpected error (status ${status}).`;
                }

                showMessage(msg, 'error');
                console.error(`${context} error [${status}]:`, error);
            }

            bKash.init({
                paymentMode: 'checkout',
                paymentRequest: {
                    amount: payAmount,
                    intent: 'sale',
                    currency: 'BDT'
                },
                createRequest: function (request) {
                    clearMessage();

                    axios.post(`${baseUrl}/api/gateway/bkash/create`, {
                        amount: payAmount
                    })
                        .then(response => {
                            const data = response.data;

                            if (data.paymentID) {
                                paymentID = data.paymentID;
                                tranId = data.tran_id;
                                const bkashURL = data.bkashURL || data.paymentURL;

                                if (bkashMode === 'sandbox') {
                                    window.location.href = bkashURL;
                                } else {
                                    bKash.create().onSuccess(data);
                                }
                            } else {
                                showMessage('Failed to initiate payment.', 'error');
                                bKash.create().onError();
                            }
                        })
                        .catch(error => {
                            handleAxiosError(error, "Create Payment");
                            bKash.create().onError();
                        });
                },
                executeRequestOnAuthorization: function () {
                    clearMessage();

                    axios.post(`${baseUrl}/api/gateway/bkash/execute`, {
                        transaction_id: tranId
                    })
                        .then(response => {
                            const data = response.data;

                            if (data.paymentID && data.transactionStatus === 'Completed') {
                                showMessage("✅ Payment successful!", "success");
                            } else {
                                showMessage("Payment failed or not completed.", "error");
                            }
                        })
                        .catch(error => {
                            handleAxiosError(error, "Execute Payment");
                        });
                },
                onClose: function () {
                    redirectToHome();
                }
            });

            $('#payBtn').on('click', function () {
                $('#bKash_button').trigger('click');
            });
        });
    </script>
```

---

## ⚛️ Example: ReactJS Frontend Integration

1. Use the `/api/gateway/bkash/create` endpoint to get `bkashURL`.
2. Redirect to that URL using `window.location.href`.

```js
import React, { useEffect } from 'react';
import axios from 'axios';

const BkashCheckout = ({
  amount = 499,
  baseUrl = 'http://payment-gateway.test',
  token = '',
  mode = 'sandbox',
}) => {
  useEffect(() => {
    const script = document.createElement('script');
    script.src =
      mode === 'sandbox'
        ? 'https://scripts.sandbox.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout-sandbox.js'
        : 'https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js';
    script.async = true;
    script.onload = () => initializeBkash();
    document.body.appendChild(script);
  }, []);

  const initializeBkash = () => {
    let paymentID = null;
    let tranId = '';

    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    axios.defaults.headers.common['Content-Type'] = 'application/json';

    window.bKash.init({
      paymentMode: 'checkout',
      paymentRequest: {
        amount: amount.toString(),
        intent: 'sale',
        currency: 'BDT',
      },
      createRequest: (request) => {
        axios
          .post(`${baseUrl}/api/gateway/bkash/create`, { amount })
          .then((res) => {
            const data = res.data;
            if (data.paymentID) {
              paymentID = data.paymentID;
              tranId = data.tran_id;
              const bkashURL = data.bkashURL || data.paymentURL;
              if (mode === 'sandbox') {
                window.location.href = bkashURL;
              } else {
                window.bKash.create().onSuccess(data);
              }
            } else {
              alert('Failed to initiate payment.');
              window.bKash.create().onError();
            }
          })
          .catch((err) => {
            console.error('Create Payment Error:', err);
            window.bKash.create().onError();
          });
      },
      executeRequestOnAuthorization: () => {
        axios
          .post(`${baseUrl}/api/gateway/bkash/execute`, { transaction_id: tranId })
          .then((res) => {
            const data = res.data;
            if (data.paymentID && data.transactionStatus === 'Completed') {
              alert('✅ Payment successful!');
            } else {
              alert('❌ Payment failed or not completed.');
            }
          })
          .catch((err) => {
            console.error('Execute Payment Error:', err);
          });
      },
      onClose: () => {
        // cancel is build in method you can skip this
         axios
          .post(`${baseUrl}/api/gateway/bkash/cancel`, { transaction_id: tranId })
          .then((res) => {
            const data = res.data;
            if (data.paymentID && data.transactionStatus === 'Completed') {
              alert('✅ Payment successful!');
            } else {
              alert('❌ Payment failed or not completed.');
            }
          })
          .catch((err) => {
            console.error('Execute Payment Error:', err);
          });
      },
        window.location.href = '/';
      },
    });
  };

  const handleClick = () => {
    const bkashBtn = document.getElementById('bKash_button');
    if (bkashBtn) bkashBtn.click();
  };

  return (
    <div className="bg-white max-w-sm mx-auto p-6 rounded shadow text-center">
      <img
        src="https://placehold.co/400x300"
        alt="Product"
        className="w-full h-48 object-cover rounded mb-4"
      />
      <h2 className="text-xl font-semibold mb-2">Awesome Product</h2>
      <p className="text-gray-600 mb-2">Buy the best product with confidence.</p>
      <p className="text-lg font-bold mb-4">৳ {amount}</p>
      <button
        className="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded w-full"
        onClick={handleClick}
      >
        Buy Now
      </button>
      <button id="bKash_button" className="hidden"></button>
    </div>
  );
};

export default BkashCheckout;

```
---

### 🧩 Build Custom Payment Logic

You can build your own custom flow using the methods exposed by `BdPayment::bkash()`. Below are examples of how to use them in your controller or service layer.

---

### 🛒 1. Create a Payment

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RmdMostakim\BdPayment\Facades\BdPayment;

public function manualCheckout(Request $request)
{
    $user = Auth::user();
    $invoice = 'INV-' . strtoupper(uniqid());
    $amount = $request->input('amount', 100);

    $response = BdPayment::bkash()->createPayment([
        'user_id' => $user->id,
        'amount'  => $amount,
        'invoice' => $invoice,
    ]);

    if (isset($response['bkashURL'])) {
        return redirect()->away($response['bkashURL']);
    }

    return response()->json([
        'message' => 'Payment initialized',
        'data'    => $response,
    ]);
}
```

---

### ✅ 2. Execute a Payment

This should be called after the user authorizes the payment on bKash's popup.

```php
public function executePayment(Request $request)
{
    $transactionId = $request->input('transaction_id');

    $response = BdPayment::bkash()->executePayment($transactionId);

    if ($response['transactionStatus'] === 'Completed') {
        // Payment successful, update your records
        return response()->json(['message' => 'Payment successful', 'data' => $response]);
    }

    return response()->json(['message' => 'Payment not completed', 'data' => $response], 400);
}
```

---

### 🔍 3. Verify a Payment

You can use this to confirm the final status of a payment at any point.

```php
public function verifyPayment(Request $request)
{
    $transactionId = $request->input('transaction_id');

    $verification = BdPayment::bkash()->verifyPayment($transactionId);

    if (isset($verification['transactionStatus']) && $verification['transactionStatus'] === 'Completed') {
        return response()->json(['message' => 'Payment verified successfully', 'data' => $verification]);
    }

    return response()->json(['message' => 'Payment not verified or failed', 'data' => $verification], 400);
}
```

---

### 📥 `createPayment()` Parameters

This method accepts an array with the following keys:

| Key       | Type   | Required | Description                                      |
|-----------|--------|----------|--------------------------------------------------|
| `user_id` | int    | ✅ Yes   | Authenticated user’s ID                         |
| `amount`  | float  | ✅ Yes   | Amount to be paid (in BDT)                      |
| `invoice` | string | ✳️ Optional | Custom invoice number (auto-generated if empty) |

---

### 🧠 Tips

- 🧠 If you don’t pass `invoice`, it will automatically generate a unique one.
- 📌 If you want to redirect to bKash's payment page, use `bkashURL`.
  If you are handling checkout through your own domain, see the section below for an example.

---

## 🧱 Configuration

`config/bdpayment.php`:

```php
return [
    'default' => 'bkash',
    'drivers' => [
        'bkash' => [...],
        'nagad' => [...], // Coming soon
    ]
];
```

---

## 🧰 Roadmap

- [x] bKash Checkout
- [ ] Nagad Integration
- [ ] SSLCommerz
- [ ] Rocket, DBBL, City Bank
- [ ] Webhooks
- [ ] Multi-tenancy support

---

## 🤝 Contributing

Pull requests are welcome!  
Please open an issue first to discuss major changes.

---

## 📄 License

MIT © [Mostakim Rahman](https://github.com/Rmdmostakim)
