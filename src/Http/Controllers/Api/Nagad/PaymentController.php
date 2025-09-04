<?php

/**
 * PaymentController (Nagad)
 *
 * Handles API endpoints for Nagad payment creation and callback.
 * Provides validation, logging, and frontend callback redirection.
 *
 * @package RmdMostakim\BdPayment\Http\Controllers\Api\Nagad
 */

namespace RmdMostakim\BdPayment\Http\Controllers\Api\Nagad;

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
 * @method \Illuminate\Http\JsonResponse create(Request $request, PaymentManager $paymentManager)
 * @method \Illuminate\Http\RedirectResponse callback(Request $request, PaymentManager $paymentManager)
 */
class PaymentController extends Controller
{
    use InteractsWithFrontend;

    /**
     * Create a new Nagad payment.
     * Validates request, logs, initializes and executes payment.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function create(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[Nagad: PaymentController@create] Request data:', $request->all());

        $validato = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer'],
        ]);

        if ($validato->fails()) {
            $error = $validato->errors()->first();
            Log::debug("[Nagad: PaymentController@create] Validation failed: $error");

            return response()->json([
                'error' => $error,
            ], 422);
        }
        $product_id = $request->product_id ?? null;
        $invoice = $request->invoice ?? Bdpayment::generateUniqueInvoiceId();

        $amount = $request->amount;
        // Try to get authenticated user, fallback to user_id lookup
        $user = Auth::guard('sanctum')->user()
            ?? Auth::user()
            ?? ($request->user_id ? DB::table('users')->where('id', $request->user_id)->first() : null);
        $user_id = $user?->id;
        Log::debug("[Nagad: PaymentController@create] Creating payment for user: $user_id, amount: $amount, invoice: $invoice");

        try {
            $initialize = $paymentManager->nagad()->initializePayment($invoice);
            $payload = [
                'user_id' => $user_id,
                'invoice' => $invoice,
                'amount'  => $amount,
                'product_id' => $product_id,
                'transaction_id' => $initialize['paymentReferenceId'],
                'challenge' => $initialize['challenge']
            ];
            $response = $paymentManager->nagad()->executePayment($payload);
            Log::debug('[Nagad: PaymentController@create] Payment creation response:', ['response' => $response]);

            return $response;
        } catch (\Exception $e) {
            Log::error("[Nagad: PaymentController@create] Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Nagad payment callback.
     * Verifies payment, updates status, and redirects to frontend callback.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[Nagad: PaymentController@callback] Callback request received', $request->all());
        $paymentId = $request->input('payment_ref_id');
        $invoice = $request->input('order_id');

        if (!$paymentId && !$invoice) {
            Log::warning('[Nagad: PaymentController@callback] Missing transaction ID.');
            return redirect()->away(
                $this->frontendCallback('', 'failed', 'Invalid or missing transaction.')
            );
        }

        try {
            $verification = $paymentManager->nagad()->verifyPayment($paymentId);

            // Find payment by transaction_id if available, otherwise by invoice
            if ($paymentId) {
                $payment = Bdpayment::where('transaction_id', $paymentId)->first();
            } else {
                $payment = Bdpayment::where('invoice', $invoice)->first();
            }
            $invoice = $payment ? $payment->invoice : '';

            if (!$payment) {
                Log::warning('[Nagad: PaymentController@callback] Payment not found.', [
                    'transaction_id' => $paymentId,
                    'verification' => $verification,
                ]);
                return redirect()->away(
                    $this->frontendCallback($invoice, 'failed', 'Payment not found.')
                );
            }

            // Nagad returns 'status' => 'success' for completed payments
            $isCompleted = isset($verification['status']) &&
                strtolower($verification['status']) === 'success';

            $payment->status = $isCompleted ? 'completed' : 'failed';
            $payment->save();

            Log::info('[Nagad: PaymentController@callback] Payment status updated.', [
                'transaction_id' => $paymentId,
                'status' => $payment->status,
                'verification' => $verification,
            ]);

            return redirect()->away(
                $this->frontendCallback(
                    $invoice,
                    $isCompleted ? 'success' : 'failed',
                    $isCompleted ? 'Payment completed.' : 'Payment failed.'
                )
            );
        } catch (\Throwable $e) {
            Log::error('[Nagad: PaymentController@callback] Exception thrown during verification.', [
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
