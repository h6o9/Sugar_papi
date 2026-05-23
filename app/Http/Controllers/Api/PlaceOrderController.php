<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiOrderPlacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class PlaceOrderController extends Controller
{
    public function __construct(
        private ApiOrderPlacementService $orderService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/place-order
    //
    // CRITICAL DESIGN:
    //   We validate EVERYTHING before creating the Stripe session.
    //   If any check fails → clear error shown to user → NO payment page opened.
    //   This prevents the scenario: payment succeeds but order cannot be created.
    //
    // Flow:
    //   1. Auth check
    //   2. Pre-flight validation (cart, products, address, points, minimums)
    //   3. Only if ALL checks pass → create Stripe session
    //   4. Return payment URL to the app
    // ─────────────────────────────────────────────────────────────────────────
    public function placeOrder(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'code'    => 'UNAUTHORIZED',
                'message' => 'You are not logged in. Please log in again and try.',
            ], 401);
        }

        try {
            // ── STEP 1: Pre-flight validation (BEFORE touching Stripe) ────────
            // If anything here fails, the user sees a clear error message and
            // NO Stripe session is created — no money is charged.
            $validationResult = $this->orderService->validateOrderBeforePayment($user->id, $request);

            if (!$validationResult['valid']) {
                Log::warning('PlaceOrder: pre-flight validation failed', [
                    'user_id' => $user->id,
                    'code'    => $validationResult['code'],
                    'message' => $validationResult['message'],
                ]);

                return response()->json([
                    'status'  => false,
                    'code'    => $validationResult['code'],
                    'message' => $validationResult['message'],
                ], 422);
            }

            // ── STEP 2: All validations passed → initiate Stripe checkout ────
            return $this->initiateStripeCheckout($request, $user->id, $validationResult['data']);

        } catch (\RuntimeException $e) {
            Log::warning('PlaceOrder: RuntimeException', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => false,
                'code'    => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('PlaceOrder: unexpected server error', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => false,
                'code'    => 'SERVER_ERROR',
                'message' => 'A server error occurred. Please try again in a moment.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: Create Stripe checkout session
    // Only called AFTER all pre-flight validations pass.
    // $preValidatedData is already calculated — no need to recalculate totals.
    // ─────────────────────────────────────────────────────────────────────────
    private function initiateStripeCheckout(Request $request, int $userId, array $preValidatedData)
    {
        $finalTotal        = max(0, (float) $preValidatedData['estimatedTotal']);
        $gatewayFee        = round(($finalTotal * 0.025) + 0.25, 2);
        $finalTotalWithFee = round($finalTotal + $gatewayFee, 2);

        // ── Save points_to_redeem into cart rows for this user ────────────────
        $pointsToRedeem = (int) $request->input('points_to_redeem', 0);
        DB::table('add_to_cart_items')
            ->where('user_id', $userId)
            ->update(['points_to_redeem' => $pointsToRedeem]);

        // ── Store order context in Stripe metadata ────────────────────────────
        // Cart items are NOT stored here — on success we re-read from DB.
        // We DO store the expected_total as a tamper-detection snapshot.
        $metaOrderType       = (string) ($request->input('order_type',       'delivery'));
        $metaDeliveryAddress = (string) ($request->input('delivery_address', ''));
        $metaDeliveryCharges = (string) ($request->input('delivery_charges', '0'));
        $metaTip             = (string) ($request->input('tip',              '0'));
        $metaBranchId        = (string) ($request->input('branch_id',        ''));
        $metaVehicleColor    = (string) ($request->input('vehicle_color',    ''));
        $metaVehicleNumber   = (string) ($request->input('vehicle_number',   ''));
        $metaPickupTime      = (string) ($request->input('pickup_time',      ''));

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'gbp',
                        'product_data' => ['name' => 'Order Payment'],
                        'unit_amount'  => $this->orderService->amountToStripePence($finalTotalWithFee),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'user_id'          => (string) $userId,
                    'gateway_fee'      => (string) $gatewayFee,
                    'expected_total'   => (string) round($preValidatedData['estimatedTotal'], 2),
                    'expected_subtotal'=> (string) round($preValidatedData['subtotal'], 2),
                    'order_type'       => $metaOrderType,
                    'delivery_address' => $metaDeliveryAddress,
                    'delivery_charges' => $metaDeliveryCharges,
                    'tip'              => $metaTip,
                    'branch_id'        => $metaBranchId,
                    'vehicle_color'    => $metaVehicleColor,
                    'vehicle_number'   => $metaVehicleNumber,
                    'pickup_time'      => $metaPickupTime,
                ],
                'success_url' => url('/api/payment/stripe/webview/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url'  => url('/api/payment/stripe/webview/cancel'),
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('PlaceOrder: Stripe session creation failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => false,
                'code'    => 'STRIPE_ERROR',
                'message' => 'Could not connect to the payment service. Please try again.',
            ], 502);
        }

        Log::info('PlaceOrder: Stripe session created', [
            'user_id'    => $userId,
            'session_id' => $session->id,
            'amount'     => $finalTotalWithFee,
        ]);

        return response()->json([
            'status'              => true,
            'message'             => 'Complete your payment to place the order.',
            'payment_required'    => true,
            'stripe_checkout_url' => $session->url,
            'payment_url'         => url('/api/payment/stripe/webview/direct?stripe_session_id=' . $session->id),
            'summary' => [
                'subtotal'         => round($preValidatedData['subtotal'], 2),
                'tax'              => round($preValidatedData['totalTax'], 2),
                'tip'              => round($preValidatedData['tip'], 2),
                'delivery_charge'  => round($preValidatedData['deliveryCharges'], 2),
                'points_discount'  => round($preValidatedData['pointsDiscount'], 2),
                'total_before_fee' => round($finalTotal, 2),
                'gateway_fee'      => $gatewayFee,
                'final_total'      => $finalTotalWithFee,
            ],
        ]);
    }
}