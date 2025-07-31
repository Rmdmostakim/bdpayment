<?php

namespace RmdMostakim\BdPayment\Http\Controllers\Web\Bkash;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RmdMostakim\BdPayment\Models\Bdpayment;
use RmdMostakim\BdPayment\PaymentManager;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        Log::debug('[PaymentController@index] Request received', $request->only('amount'));

        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $amount = $request->amount;
        Log::debug("[PaymentController@index] Validated amount: $amount");

        return view('bdpayment::bkash.index', compact('amount'));
    }

    public function create(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[PaymentController@create] Request data:', $request->all());

        $validato = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        if ($validato->fails()) {
            $error = $validato->errors()->first();
            Log::debug("[PaymentController@create] Validation failed: $error");

            return response()->json([
                'error' => $error,
            ], 422);
        }

        $amount = $request->amount;
        if (!auth()->check()) {
            Log::warning('[PaymentController@create] Unauthorized access attempt');

            return response()->json([
                'error' => 'User is not authenticated.',
            ], 403);
        }
        $user_id = auth()->user()->id;
        Log::debug("[PaymentController@create] Creating payment for user: $user_id, amount: $amount");

        try {
            $response = $paymentManager->bkash()->createPayment(['user_id' => $user_id, 'amount' => $amount]);
            Log::debug('[PaymentController@create] Payment creation response:', ['response' => $response]);

            return $response;
        } catch (\Exception $e) {
            Log::error("[PaymentController@create] Exception: " . $e->getMessage());

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function execute(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[PaymentController@execute] Request data:', $request->all());

        $validato = Validator::make($request->all(), [
            'transaction_id' => ['required', 'string'],
        ]);

        if ($validato->fails()) {
            $error = $validato->errors()->first();
            Log::debug("[PaymentController@execute] Validation failed: $error");

            return response()->json([
                'error' => $error,
            ], 422);
        }

        $tranId = $request->transaction_id;
        Log::debug("[PaymentController@execute] Executing transaction: $tranId");

        try {
            $response = $paymentManager->bkash()->executePayment($tranId);
            Log::debug('[PaymentController@execute] Execution response:', ['response' => $response]);

            return $response;
        } catch (\Exception $e) {
            Log::error("[PaymentController@execute] Exception: " . $e->getMessage());

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function callback(Request $request, PaymentManager $paymentManager)
    {
        Log::debug('[PaymentController@callback] Callback request received', $request->all());

        $transactionId = $request->input('paymentID');

        if (!$transactionId) {
            Log::warning('[PaymentController@callback] Missing transaction ID.');
            return view('bdpayment::callback', [
                'status' => 'failed',
                'transaction_id' => null,
                'message' => 'Invalid or missing transaction ID.'
            ]);
        }
        try {
            $verification = $paymentManager->bkash()->verifyPayment($transactionId);

            if (
                isset($verification['verificationStatus']) &&
                $verification['verificationStatus'] === 'Complete'
            ) {
                $payment = Bdpayment::where('transaction_id', $transactionId)->firstOrFail();
                $payment->status = 'completed';
                $payment->save();

                Log::info('[PaymentController@callback] Payment verified and marked as completed.', [
                    'transaction_id' => $transactionId,
                ]);

                return view('bdpayment::bkash.callback', [
                    'status' => 'success',
                    'transaction_id' => $transactionId,
                ]);
            }

            Log::warning('[PaymentController@callback] Payment verification failed.', [
                'transaction_id' => $transactionId,
                'verification' => $verification,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PaymentController@callback] Exception thrown during verification.', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }
        return view('bdpayment::bkash.callback', [
            'status' => 'failed',
            'transaction_id' => $transactionId,
            'message' => 'Payment could not be verified.',
        ]);
    }
}
