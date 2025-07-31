<?php

namespace RmdMostakim\BdPayment\Http\Controllers\Api\Bkash;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RmdMostakim\BdPayment\Models\Bdpayment;
use RmdMostakim\BdPayment\PaymentManager;

class PaymentController extends Controller
{
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
        $user = Auth::guard('sanctum')->user() ?? Auth::guard('api')->user();

        if (!$user) {
            Log::warning('[PaymentController@create] Unauthorized access attempt');

            return response()->json([
                'error' => 'User is not authenticated.',
            ], 403);
        }
        $user_id = $user->id;
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
            return response([
                'status' => 'failed',
                'transaction_id' => null,
                'message' => 'Invalid or missing transaction ID.'
            ], 400);
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

                return response([
                    'status' => 'success',
                    'transaction_id' => $transactionId,
                ], 200);
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
        return response([
            'status' => 'failed',
            'transaction_id' => $transactionId,
            'message' => 'Payment could not be verified.',
        ], 400);
    }
}
