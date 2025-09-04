<?php

/**
 * PaymentController (Bkash)
 *
 * Handles API endpoints for Bkash payment creation, execution, and callback.
 * Provides validation, logging, and frontend callback redirection.
 *
 * @package RmdMostakim\BdPayment\Http\Controllers\Api\Bkash
 */

namespace RmdMostakim\BdPayment\Http\Controllers\Api\Bkash;

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
 * @method \Illuminate\Http\JsonResponse execute(Request $request, PaymentManager $paymentManager)
 * @method \Illuminate\Http\RedirectResponse|string callback(Request $request, PaymentManager $paymentManager)
 */
class PaymentController extends Controller
{
    use InteractsWithFrontend;

    /**
     * Create a new Bkash payment.
     * Validates request, logs, and calls the payment manager.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function create(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[Bkash: PaymentController@create] Request data:', $request->all());

        $validato = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer'],
        ]);

        if ($validato->fails()) {
            $error = $validato->errors()->first();
            Log::debug("[Bkash: PaymentController@create] Validation failed: $error");

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
        Log::debug("[Bkash: PaymentController@create] Creating payment for user: $user_id, amount: $amount, invoice: $invoice, product_id: $product_id");

        try {
            $response = $paymentManager->bkash()->createPayment(['user_id' => $user_id, 'amount' => $amount, 'invoice' => $invoice, 'product_id' => $product_id]);
            Log::debug('[Bkash: PaymentController@create] Payment creation response:', ['response' => $response]);

            return $response;
        } catch (\Exception $e) {
            Log::error("[Bkash: PaymentController@create] Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute a Bkash payment.
     * Validates request, logs, and calls the payment manager.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function execute(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[Bkash: PaymentController@execute] Request data:', $request->all());

        $validato = Validator::make($request->all(), [
            'paymentID' => ['required', 'string'],
        ]);

        if ($validato->fails()) {
            $error = $validato->errors()->first();
            Log::debug("[Bkash: PaymentController@execute] Validation failed: $error");

            return response()->json([
                'error' => $error,
            ], 422);
        }

        $tranId = $request->paymentID;
        Log::debug("[Bkash: PaymentController@execute] Executing transaction: $tranId");

        try {
            $response = $paymentManager->bkash()->executePayment($tranId);
            Log::debug('[Bkash: PaymentController@execute] Execution response:', ['response' => $response]);

            return $response;
        } catch (\Exception $e) {
            Log::error("[Bkash: PaymentController@execute] Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Bkash payment callback.
     * Verifies payment, updates status, and redirects to frontend callback.
     *
     * @param Request $request
     * @param PaymentManager $paymentManager
     * @return string|\Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[Bkash: PaymentController@callback] Callback request received', $request->all());

        $paymentId = $request->input('paymentID');

        if (!$paymentId) {
            Log::warning('[Bkash: PaymentController@callback] Missing transaction ID.');
            return $this->frontendCallback('', 'failed', 'Invalid or missing transaction.');
        }

        try {
            $verification = $paymentManager->bkash()->verifyPayment($paymentId);

            $payment = Bdpayment::where('transaction_id', $paymentId)->first();
            $invoice = $payment ? $payment->invoice : '';

            if (!$payment) {
                Log::warning('[Bkash: PaymentController@callback] Payment not found.', [
                    'transaction_id' => $paymentId,
                    'verification' => $verification,
                ]);
                return $this->frontendCallback($invoice, 'failed', 'Payment not found.');
            }

            $isCompleted = isset($verification['verificationStatus']) &&
                strtolower($verification['verificationStatus']) === 'complete';

            $payment->status = $isCompleted ? 'completed' : 'failed';
            $payment->save();

            Log::info('[Bkash: PaymentController@callback] Payment status updated.', [
                'transaction_id' => $paymentId,
                'status' => $payment->status,
                'verification' => $verification,
            ]);

            return $this->frontendCallback(
                $invoice,
                $isCompleted ? 'success' : 'failed',
                $isCompleted ? 'Payment completed.' : 'Payment failed.'
            );
        } catch (\Throwable $e) {
            Log::error('[Bkash: PaymentController@callback] Exception thrown during verification.', [
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
