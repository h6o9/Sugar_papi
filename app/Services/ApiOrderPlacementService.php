<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemToppings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ApiOrderPlacementService
{
    private const STRIPE_MIN_GBP = 0.30;

    // ─────────────────────────────────────────────────────────────────────────
    // PRE-FLIGHT VALIDATION
    //
    // Called BEFORE creating the Stripe session. If this returns valid=false,
    // the user sees a clear English error message and NO payment page is shown.
    // This is the primary guard that prevents "payment succeeded but order
    // creation failed" scenarios.
    //
    // Returns:
    //   ['valid' => true,  'data' => $calculatedOrderData]
    //   ['valid' => false, 'code' => 'ERROR_CODE', 'message' => 'English message']
    // ─────────────────────────────────────────────────────────────────────────
    public function validateOrderBeforePayment(int $userId, Request $request): array
    {
        // ── [V1] Cart must not be empty ───────────────────────────────────────
        $cartItems = DB::table('add_to_cart_items')
            ->where('user_id', $userId)
            ->get();

        if ($cartItems->isEmpty()) {
            return [
                'valid'   => false,
                'code'    => 'CART_EMPTY',
                'message' => 'Your cart is empty. Please add items before placing an order.',
            ];
        }

        // ── [V2] All cart items must have a valid product_id ──────────────────
        $invalidItems = $cartItems->filter(fn($i) => empty($i->product_id));
        if ($invalidItems->isNotEmpty()) {
            return [
                'valid'   => false,
                'code'    => 'CART_INVALID_ITEMS',
                'message' => 'Some items in your cart are invalid (missing product ID). Please clear your cart and add items again.',
            ];
        }

        // ── [V3] All products must still exist and be active ──────────────────
        $productIds       = $cartItems->pluck('product_id')->filter()->unique()->values();
        $existingProducts = DB::table('products')
            ->whereIn('id', $productIds)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();

        $missingProductIds = $productIds->diff($existingProducts);
        if ($missingProductIds->isNotEmpty()) {
            $missingNames = $cartItems
                ->whereIn('product_id', $missingProductIds->toArray())
                ->pluck('product_name')
                ->unique()
                ->implode(', ');

            return [
                'valid'   => false,
                'code'    => 'PRODUCT_UNAVAILABLE',
                'message' => 'The following item(s) are no longer available: ' . $missingNames . '. Please remove them from your cart and try again.',
            ];
        }

        // ── [V4] All cart items must have a subtotal greater than zero ────────
        $zeroSubtotalItems = $cartItems->filter(fn($i) => (float)($i->subtotal ?? 0) <= 0);
        if ($zeroSubtotalItems->isNotEmpty()) {
            $zeroNames = $zeroSubtotalItems->pluck('product_name')->unique()->implode(', ');
            return [
                'valid'   => false,
                'code'    => 'CART_ZERO_PRICE',
                'message' => 'The following item(s) have a zero price: ' . $zeroNames . '. Please clear your cart and add them again.',
            ];
        }

        // ── [V5] branch_id is required ────────────────────────────────────────
        $branchId = (int) ($request->input('branch_id') ?: $cartItems->first()->branch_id ?? 0);
        if (!$branchId) {
            return [
                'valid'   => false,
                'code'    => 'BRANCH_MISSING',
                'message' => 'Please select a branch before placing your order.',
            ];
        }

        // ── [V6] Branch must exist in the database ────────────────────────────
        $branch = DB::table('branches')->where('id', $branchId)->first();
        if (!$branch) {
            return [
                'valid'   => false,
                'code'    => 'BRANCH_NOT_FOUND',
                'message' => 'The selected branch could not be found. Please go back and select a branch again.',
            ];
        }

        // ── [V7] Delivery address is required for delivery orders ─────────────
        $orderType       = $request->input('order_type', $cartItems->first()->order_type ?? 'delivery');
        $deliveryAddress = $request->input('delivery_address', $cartItems->first()->delivery_address ?? '');

        if (in_array($orderType, ['delivery', 'home'], true) && empty(trim($deliveryAddress))) {
            return [
                'valid'   => false,
                'code'    => 'DELIVERY_ADDRESS_MISSING',
                'message' => 'A delivery address is required for delivery orders. Please enter your delivery address.',
            ];
        }

        // ── [V8] Points to redeem must be a non-negative number ───────────────
        $pointsToRedeem = (int) $request->input('points_to_redeem', 0);
        if ($pointsToRedeem < 0) {
            return [
                'valid'   => false,
                'code'    => 'POINTS_INVALID',
                'message' => 'The points amount is invalid. Please enter a valid number of points to redeem.',
            ];
        }

        // ── [V9] User must have enough points ─────────────────────────────────
        if ($pointsToRedeem > 0) {
            $userRewards = DB::table('rewards')->where('user_id', $userId)->first();
            $available   = (int) ($userRewards->rewards ?? 0);

            if (!$userRewards || $available < $pointsToRedeem) {
                return [
                    'valid'   => false,
                    'code'    => 'INSUFFICIENT_POINTS',
                    'message' => 'Insufficient points. You have ' . $available . ' points available but tried to redeem ' . $pointsToRedeem . '.',
                ];
            }

            // ── [V9b] Reward settings must be configured ──────────────────────
            $rewardSetting = DB::table('reward_settings')->orderBy('id', 'desc')->first();
            if (!$rewardSetting) {
                return [
                    'valid'   => false,
                    'code'    => 'REWARD_SETTINGS_MISSING',
                    'message' => 'The reward settings are not configured on our system. Please place your order without points, or contact support.',
                ];
            }
        }

        // ── [V10] Calculate totals and validate Stripe minimum charge ─────────
        // calculateOrderData also validates points discount vs order total.
        try {
            $data = $this->calculateOrderData($userId, $request);
        } catch (\RuntimeException $e) {
            return [
                'valid'   => false,
                'code'    => 'CALCULATION_ERROR',
                'message' => $e->getMessage(),
            ];
        }

        $finalTotal        = max(0, (float) $data['estimatedTotal']);
        $gatewayFee        = round(($finalTotal * 0.025) + 0.25, 2);
        $finalTotalWithFee = round($finalTotal + $gatewayFee, 2);

        if ($finalTotalWithFee < self::STRIPE_MIN_GBP) {
            return [
                'valid'   => false,
                'code'    => 'BELOW_MINIMUM',
                'message' => 'The minimum order amount for card payment is £' . number_format(self::STRIPE_MIN_GBP, 2) . '. Please add more items to your cart.',
            ];
        }

        // ── [V11] Points discount must not exceed order total ─────────────────
        if ((float) ($data['pointsDiscount'] ?? 0) > $finalTotal + 0.01) {
            return [
                'valid'   => false,
                'code'    => 'POINTS_EXCEED_TOTAL',
                'message' => 'Your points discount (£' . number_format($data['pointsDiscount'], 2) . ') cannot exceed your order total (£' . number_format($finalTotal, 2) . '). Please reduce the points you want to redeem.',
            ];
        }

        // All checks passed
        return [
            'valid' => true,
            'data'  => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function assertStripeMinimum(float $amountGbp): void
    {
        if ($amountGbp < self::STRIPE_MIN_GBP) {
            throw new \RuntimeException(
                'Order total must be at least £' . number_format(self::STRIPE_MIN_GBP, 2) . ' to pay by card.'
            );
        }
    }

    public function amountToStripePence(float $amountGbp): int
    {
        return (int) round($amountGbp * 100);
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function calculateOrderData(int $userId, Request $request): array
    {
        $cartItems = DB::table('add_to_cart_items')
            ->where('user_id', $userId)
            ->get();

        if ($cartItems->isEmpty()) {
            throw new \RuntimeException('Your cart is empty. Please add items before placing an order.');
        }

        $this->mergeRequestWithCartDefaults($request, $cartItems);

        $subtotal  = $cartItems->sum('subtotal');
        $totalTax  = $cartItems->sum('tax_amount');
        $totalTips = $cartItems->sum('tips');

        $orderType       = $request->input('order_type', $cartItems->first()->order_type ?? 'delivery');
        $deliveryAddress = $request->input('delivery_address', $cartItems->first()->delivery_address);
        $branchId        = (int) ($request->input('branch_id') ?: $cartItems->first()->branch_id);
        $branch          = $branchId ? DB::table('branches')->where('id', $branchId)->first() : null;

        $deliveryCharges = (float) $request->input('delivery_charges', 0);
        $tip             = (float) $request->input('tip', $cartItems->first()->tips ?? 0);

        if (in_array($orderType, ['delivery', 'home'], true)) {
            if ($branch && $deliveryAddress) {
                $distanceValue = $this->calculateDistanceInMiles($branch->location, $deliveryAddress);
                if ($distanceValue !== null) {
                    $deliveryCharges = match(true) {
                        $distanceValue <= 1 => 1.99,
                        $distanceValue <= 2 => 2.99,
                        $distanceValue <= 3 => 3.49,
                        $distanceValue <= 5 => 4.99,
                        default             => 5.99,
                    };
                }
            }
        } elseif ($orderType === 'pickup') {
            $deliveryCharges = 0;
            if (empty($deliveryAddress) && $branch) {
                $deliveryAddress = $branch->location;
            }
        }

        $deliveryStatus = $orderType === 'pickup' ? '1' : '2';
        $estimatedTotal = $subtotal + $totalTax + $tip + $deliveryCharges;

        $pointsToRedeem = (int) $request->input(
            'points_to_redeem',
            $cartItems->first()->points_to_redeem ?? 0
        );

        $pointsDiscount = 0;
        $userRewards    = null;

        if ($pointsToRedeem > 0) {
            $userRewards = DB::table('rewards')->where('user_id', $userId)->first();

            if (!$userRewards || (int) $userRewards->rewards < $pointsToRedeem) {
                throw new \RuntimeException(
                    'Insufficient points. You have ' . ($userRewards->rewards ?? 0) . ' points available.'
                );
            }

            $rewardSetting = DB::table('reward_settings')->orderBy('id', 'desc')->first();
            if (!$rewardSetting) {
                throw new \RuntimeException('Reward settings are not configured. Please contact support.');
            }

            $pointsDiscount = $pointsToRedeem * (float) $rewardSetting->price;

            if ($pointsDiscount > $estimatedTotal) {
                throw new \RuntimeException(
                    'Your points discount (£' . number_format($pointsDiscount, 2) . ') cannot exceed your order total (£' . number_format($estimatedTotal, 2) . ').'
                );
            }

            $estimatedTotal -= $pointsDiscount;
        }

        return compact(
            'cartItems',
            'subtotal',
            'totalTax',
            'totalTips',
            'orderType',
            'deliveryAddress',
            'branchId',
            'branch',
            'deliveryCharges',
            'tip',
            'deliveryStatus',
            'estimatedTotal',
            'pointsToRedeem',
            'pointsDiscount',
            'userRewards'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE ORDER — only called after payment_status === 'paid' is confirmed.
    //
    // ATOMIC TRANSACTION:
    //   - Order header, order items, and toppings are all written inside one
    //     DB transaction.
    //   - The cart is deleted ONLY as the last step before commit.
    //   - If anything fails → DB::rollBack() → cart is NOT deleted, no partial
    //     order exists, user can retry without losing their cart.
    // ─────────────────────────────────────────────────────────────────────────
    public function createOrderFromCart(
        int    $userId,
        Request $request,
        string $payment,
        ?string $stripeSessionId = null,
        ?float  $gatewayFee      = null
    ): Order {

        // ── Guard: cart must exist ────────────────────────────────────────────
        $cartItems = DB::table('add_to_cart_items')
            ->where('user_id', $userId)
            ->get();

        if ($cartItems->isEmpty()) {
            // Last-chance idempotency check before throwing
            if ($stripeSessionId && Schema::hasColumn('orders', 'stripe_session_id')) {
                $existing = Order::where('stripe_session_id', $stripeSessionId)->first();
                if ($existing) {
                    Log::info('createOrderFromCart: cart empty but existing order found (idempotency)', [
                        'order_id' => $existing->id,
                    ]);
                    return $existing;
                }
            }
            throw new \RuntimeException(
                'Your cart is empty. Your payment was received but your order may have already been placed. '
                . 'Please check "My Orders" in the app or contact support with your payment reference.'
            );
        }

        $data = $this->calculateOrderData($userId, $request);
        extract($data);

        // ── ATOMIC TRANSACTION ────────────────────────────────────────────────
        DB::beginTransaction();

        try {
            $firstItem = $cartItems->first();

            // Unique order code
            do {
                $orderCode = random_int(10000000, 99999999);
            } while (Order::where('code', $orderCode)->exists());

            // Order header
            $order          = new Order();
            $order->code    = $orderCode;
            $order->user_id = $userId;
            $order->status  = 'Pending';
            $order->payment = $payment;

            $this->setOrderColumn($order, 'product_id',      $firstItem->product_id);
            $this->setOrderColumn($order, 'vehicle_color',   $request->vehicle_color);
            $this->setOrderColumn($order, 'vehicle_number',  $request->vehicle_number);
            $this->setOrderColumn($order, 'subtotal',        $subtotal);
            $this->setOrderColumn($order, 'tax',             $totalTax);
            $this->setOrderColumn($order, 'estimated_total', $estimatedTotal);
            $this->setOrderColumn($order, 'total_amount',    $estimatedTotal + ($gatewayFee ?? 0));
            $this->setOrderColumn($order, 'delivery_charge', $deliveryCharges);
            $this->setOrderColumn($order, 'tips',            $tip);
            $this->setOrderColumn($order, 'branch_id',       $branchId ?: null);
            $this->setOrderColumn($order, 'points_redeemed', $pointsToRedeem);
            $this->setOrderColumn($order, 'points_discount', $pointsDiscount);
            $this->setOrderColumn($order, 'redeemed',        $pointsDiscount > 0 ? $pointsDiscount : $pointsToRedeem);
            $this->setOrderColumn($order, 'redeemed_points', $pointsToRedeem);

            if ($stripeSessionId) {
                $this->setOrderColumn($order, 'stripe_session_id', $stripeSessionId);
            }
            if ($gatewayFee !== null) {
                $this->setOrderColumn($order, 'gateway_fee', $gatewayFee);
            }

            $order->save();

            // Deduct redeemed points
            if ($pointsToRedeem > 0 && $userRewards) {
                $newBalance = (int) $userRewards->rewards - $pointsToRedeem;
                if ($newBalance < 0) {
                    // Race condition: another request already consumed these points
                    throw new \RuntimeException(
                        'Your points balance is insufficient to complete this redemption. Please try again without points.'
                    );
                }
                DB::table('rewards')
                    ->where('user_id', $userId)
                    ->update([
                        'rewards'    => $newBalance,
                        'redeemed'   => (int) ($userRewards->redeemed ?? 0) + $pointsToRedeem,
                        'updated_at' => now(),
                    ]);
            }

            // Order items and toppings
            $productVariants = DB::table('product_variants')
                ->whereIn('product_id', $cartItems->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            $itemBranchId = $branchId ?: null;

            foreach ($cartItems as $item) {
                $complementaryId = $item->product_complementary_id ?? null;

                if (!$complementaryId) {
                    $cartComplementary = DB::table('add_to_cart_item_toppings')
                        ->where('add_to_cart_item_id', $item->id)
                        ->whereNotNull('product_complementary_id')
                        ->first();
                    if ($cartComplementary) {
                        $complementaryId = $cartComplementary->product_complementary_id;
                    }
                }

                $productVariant = $productVariants->get($item->product_id);
                $lineBranchId   = $itemBranchId ?: ($item->branch_id ?: null);

                $orderItem                           = new OrderItem();
                $orderItem->order_id                 = $order->id;
                $orderItem->product_id               = $item->product_id;
                $orderItem->product_complementary_id = $complementaryId;
                $orderItem->product_name             = $item->product_name;
                $orderItem->branch_id                = $lineBranchId;
                $orderItem->quantity                 = $item->quantity;
                $orderItem->product_size             = $productVariant->size ?? 'Default';
                $orderItem->product_price            = $item->price;
                $orderItem->tip                      = $item->tips ?? 0;
                $orderItem->tax                      = $item->tax_amount ?? 0;
                $orderItem->delivery_status          = $deliveryStatus;
                $orderItem->delivery_address         = $deliveryAddress ?? $item->delivery_address;
                $orderItem->sub_total                = $item->subtotal ?? 0;
                $orderItem->save();

                $toppings = DB::table('add_to_cart_item_toppings as act')
                    ->join('toppings as t', 'act.topping_id', '=', 't.id')
                    ->where('act.add_to_cart_item_id', $item->id)
                    ->whereNotNull('act.topping_id')
                    ->select('act.*', 't.price as topping_price')
                    ->get();

                foreach ($toppings as $topping) {
                    $orderItemTopping                = new OrderItemToppings();
                    $orderItemTopping->order_item_id = $orderItem->id;
                    $orderItemTopping->category_id   = $topping->category_id;
                    $orderItemTopping->topping_id    = $topping->topping_id;
                    $orderItemTopping->topping_price = $topping->topping_price;
                    $orderItemTopping->save();
                }
            }

            // ── CLEAR CART — last write before commit ─────────────────────────
            // Cart is only deleted AFTER all order items and toppings are saved.
            // If anything above failed we never reach here — rollback preserves cart.
            DB::table('add_to_cart_item_toppings')
                ->whereIn('add_to_cart_item_id', $cartItems->pluck('id'))
                ->delete();

            DB::table('add_to_cart_items')
                ->where('user_id', $userId)
                ->delete();

            // Loyalty points (earning)
            $this->applyLoyaltyPoints($userId, (float) $order->total_amount);

            // COMMIT
            DB::commit();

            Log::info('createOrderFromCart: order committed successfully', [
                'order_id'   => $order->id,
                'user_id'    => $userId,
                'payment'    => $payment,
                'session_id' => $stripeSessionId,
            ]);

            return $order->fresh(['orderItem.branch']);

        } catch (\Throwable $e) {
            // ROLLBACK — nothing persisted: no order row, no items, cart untouched
            DB::rollBack();
            Log::error('createOrderFromCart: rolled back due to error', [
                'user_id'    => $userId,
                'payment'    => $payment,
                'session_id' => $stripeSessionId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function mergeRequestWithCartDefaults(Request $request, $cartItems): void
    {
        $first = $cartItems->first();
        $request->merge([
            'order_type'       => $request->input('order_type',       $first->order_type       ?? 'delivery'),
            'delivery_address' => $request->input('delivery_address', $first->delivery_address),
            'branch_id'        => $request->input('branch_id',        $first->branch_id),
            'tip'              => $request->has('tip') ? $request->input('tip') : ($first->tips ?? 0),
        ]);
    }

    private function setOrderColumn(Order $order, string $column, mixed $value): void
    {
        if (Schema::hasColumn('orders', $column)) {
            $order->{$column} = $value;
        }
    }

    private function applyLoyaltyPoints(int $userId, float $orderTotal): void
    {
        $user = User::find($userId);
        if (!$user || !Schema::hasColumn('users', 'point')) {
            return;
        }
        $existingPoints = (float) ($user->point ?? 0);
        $user->update(['point' => floor($existingPoints + $orderTotal)]);
    }

    private function calculateDistanceInMiles($origin, $destination): ?float
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins'      => $origin,
                'destinations' => $destination,
                'units'        => 'imperial',
                'key'          => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rows'][0]['elements'][0]['distance']['value'])) {
                    $meters = $data['rows'][0]['elements'][0]['distance']['value'];
                    return $meters * 0.000621371;
                }
            }
        } catch (\Exception $e) {
            Log::error('Google Maps Distance Matrix error: ' . $e->getMessage());
        }

        return null;
    }
}