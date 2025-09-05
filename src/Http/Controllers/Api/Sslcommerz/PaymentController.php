<?php

/**
 * PaymentController (SSLCommerz)
 *
 * Handles API endpoints for SSLCommerz payment creation and callback.
 * Provides validation, logging, and frontend callback redirection.
 *
 * @package RmdMostakim\BdPayment\Http\Controllers\Api\Sslcommerz
 */

namespace RmdMostakim\BdPayment\Http\Controllers\Api\Sslcommerz;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RmdMostakim\BdPayment\Models\Bdpayment;
use RmdMostakim\BdPayment\PaymentManager;
use RmdMostakim\BdPayment\Traits\InteractsWithFrontend;

/**
 * Class PaymentController
 *
 * Handles SSLCommerz payment creation and callback endpoints.
 */
class PaymentController extends Controller
{
    use InteractsWithFrontend;

    /**
     * Create a new SSLCommerz payment.
     * Validates request, logs, initializes and executes payment.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function create(Request $request, PaymentManager $paymentManager)
    {
        // Log incoming request data for debugging
        Log::debug('[SSLCommerz: PaymentController@create] Request data:', $request->all());

        // Accept both direct fields and cart_json for flexibility
        $cart = $request->has('cart_json') ? json_decode($request->cart_json, true) : $request->all();

        $validator = Validator::make(
            $cart,
            [
                'amount'     => ['required', 'numeric', 'min:1'],
                'invoice'    => ['nullable', 'string', 'max:255'],
                'user_id'    => ['nullable', 'integer', 'exists:users,id'],
                'product_id' => ['nullable', 'integer'],
            ]
        );

        // Handle validation failure
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            Log::debug("[SSLCommerz: PaymentController@create] Validation failed: $error");

            return response()->json([
                'error' => $error,
            ], 422);
        }

        // Extract fields from validated cart
        $amount     = $cart['amount'];
        $invoice    = $cart['invoice'] ?? Bdpayment::generateUniqueInvoiceId();
        $product_id = $cart['product_id'] ?? null;
        $user_id    = $cart['user_id'] ?? null;

        // Try to get authenticated user, fallback to user_id lookup
        $user = Auth::guard('sanctum')->user()
            ?? Auth::user()
            ?? ($user_id ? DB::table('users')->where('id', $user_id)->first() : null);
        $user_id = $user?->id;

        Log::debug("[SSLCommerz: PaymentController@create] Creating payment for user: $user_id, amount: $amount, invoice: $invoice");

        try {
            // Prepare payload for payment creation
            $payload = [
                'user_id'    => $user_id,
                'invoice'    => $invoice,
                'amount'     => $amount,
                'product_id' => $product_id,
            ];

            // Merge any additional custom fields from cart
            foreach ($cart as $key => $value) {
                if (!array_key_exists($key, $payload)) {
                    $payload[$key] = $value;
                }
            }

            // Create payment via driver
            $response = $paymentManager->sslcommerz()->createPayment($payload);

            Log::debug('[SSLCommerz: PaymentController@create] Payment creation response:', ['response' => $response]);

            // Return payment response (array or JsonResponse)
            return $response;
        } catch (\Exception $e) {
            // Log exception and return error response
            Log::error("[SSLCommerz: PaymentController@create] Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle SSLCommerz payment callback.
     * Verifies payment, updates status, and redirects to frontend callback.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request, PaymentManager $paymentManager)
    {
        // Log callback request data
        Log::debug('[SSLCommerz: PaymentController@callback] Callback request received', $request->all());

        // Extract payment reference and invoice from request
        $paymentId = $request->input('val_id');
        $invoice = $request->input('tran_id');

        // If both paymentId and invoice are missing, redirect with failure
        if (!$invoice) {
            Log::warning('[SSLCommerz: PaymentController@callback] Missing transaction ID.');
            return redirect()->away(
                $this->frontendCallback('', 'failed', 'Invalid or missing transaction.')
            );
        }

        try {
            // Verify payment with SSLCommerz
            $verification = $paymentManager->sslcommerz()->verifyPayment($paymentId);
            // Find payment by transaction_id if available, otherwise by invoice
            $payment = Bdpayment::where('invoice', $invoice)->first();
            $invoice = $payment ? $payment->invoice : '';

            // If payment not found, redirect with failure
            if (!$payment) {
                Log::warning('[SSLCommerz: PaymentController@callback] Payment not found.', [
                    'transaction_id' => $paymentId,
                    'verification' => $verification,
                ]);
                return redirect()->away(
                    $this->frontendCallback($invoice, 'failed', 'Payment not found.')
                );
            }

            // Check if payment is completed based on verification response
            $isCompleted = isset($verification['status']) && (strtolower($verification['status']) === 'valid' || strtolower($verification['status']) === 'validated');

            // Update payment status in database
            $payment->status = $isCompleted ? 'completed' : 'failed';
            $payment->transaction_id = $paymentId ?? null;
            $payment->save();

            Log::info('[SSLCommerz: PaymentController@callback] Payment status updated.', [
                'transaction_id' => $paymentId,
                'status' => $payment->status,
                'verification' => $verification,
            ]);

            // Redirect to frontend callback with appropriate status
            return redirect()->away(
                $this->frontendCallback(
                    $invoice,
                    $isCompleted ? 'success' : 'failed',
                    $isCompleted ? 'Payment completed.' : 'Payment failed.'
                )
            );
        } catch (\Throwable $e) {
            // Log exception and redirect with failure
            Log::error('[SSLCommerz: PaymentController@callback] Exception thrown during verification.', [
                'transaction_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->away(
                $this->frontendCallback('', 'failed', 'Payment could not be verified.')
            );
        }
    }
}
