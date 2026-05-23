<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirm;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderComplationReward;
use App\Models\OrderItem;
use App\Models\OrderItemToppings;
use App\Models\OrderCompletionRecord;
use App\Models\Reward;
use App\Models\RewardHistory;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Log;
use Square\Environment;
use Square\Exceptions\ApiException;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\SquareClient;
use App\Jobs\JobNotification;
use App\Models\Notification;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class OrderController extends Controller
{
    public function myOrder()
    {
        $orders = OrderItem::with([
            'complementaryProduct',
            'branch',
            'product.variants',
            'order.user',
            'orderToppings.category',
            'orderToppings.toppings'
        ])->latest()->get();
        // return $orders;
        return view('home.my-orders', compact('orders'));
    }


      public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Order not found']);
        }

        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Update order status
        $order->status = $newStatus;
        $order->save();

        // Get user for notification
        $user = User::find($order->user_id);

        // Send notification based on status change
        if ($user && $user->fcmtoken) {
            $title = '';
            $description = '';
            $data = ['order_id' => $order->id, 'order_code' => $order->code];

            switch ($newStatus) {
                case 'Order Ready':
                    $title = '🍔 Your Order is Ready!';
                    $description = "Your order #{$order->code} is ready for pickup/delivery.";
                    $data['status'] = 'order_ready';
                    $data['screen_name'] = 'MyOrders';
                    break;

                case 'Delivered':
                    // Process rewards for delivered order
                    $rewardPoints = 0;
                    $totalRewards = 0;
                    
                    try {
                        DB::transaction(function () use ($order, &$rewardPoints, &$totalRewards) {
                            $rewardConfig = OrderComplationReward::first();
                            $rewardPoints = $rewardConfig?->points ?? 0;

                            // Check if already rewarded
                            $orderRewardExists = OrderCompletionRecord::where('order_id', $order->id)->exists();

                            if (!$orderRewardExists && $rewardPoints > 0) {
                                // Order completion record
                                OrderCompletionRecord::create([
                                    'order_id'    => $order->id,
                                    'order_code'  => $order->code ?? null,
                                    'reward_type' => 'order_completion',
                                    'points'      => $rewardPoints,
                                ]);

                                // Reward history
                                RewardHistory::create([
                                    'reward_type'   => 'order_completion',
                                    'reward_title'  => 'Order Points Added!',
                                    'points'        => $rewardPoints,
                                    'user_id'       => $order->user_id,
                                    'description'   => 'You have earned ' . $rewardPoints . ' points for completing recent order.',
                                    'order_code'    => $order->code ?? null,
                                    'referral_code' => $order->referral_code ?? null,
                                ]);

                                // Update rewards table
                                $reward = Reward::firstOrCreate(
                                    ['user_id' => $order->user_id],
                                    ['rewards' => 0, 'redeemed' => 0]
                                );

                                $reward->increment('rewards', $rewardPoints);
                                $totalRewards = $reward->rewards;
                            }
                        });
                    } catch (\Exception $e) {
                        \Log::error('Reward processing error for order ' . $order->code . ': ' . $e->getMessage());
                    }

                    // Create notification with reward info if applicable
                    if ($rewardPoints > 0 && $totalRewards > 0) {
                        $title = '🎉 Order Delivered & Reward Earned!';
                        $description = "Your order #{$order->code} has been delivered successfully. You earned {$rewardPoints} reward points! Total rewards: {$totalRewards}";
                        $data['status'] = 'order_delivered_with_reward';
                        $data['reward_points'] = $rewardPoints;
                        $data['total_rewards'] = $totalRewards;
                         $data['screen_name'] = 'MyOrders'; 
                        
                    } else {
                        $title = '✅ Order Delivered!';
                        $description = "Your order #{$order->code} has been delivered successfully.";
                        $data['status'] = 'order_delivered';
                        $data['screen_name'] = 'MyOrders'; 

                    }
                    $data['screen_name'] = 'MyOrders'; 
                    break;

                case 'Out for Delivery':
                    $title = '🚚 Order Out for Delivery!';
                    $description = "Your order #{$order->code} is out for delivery.";
                    $data['status'] = 'out_for_delivery';
                    $data['screen_name'] = 'MyOrders';
                    break;

                default:
                    return response()->json(['status' => false, 'message' => 'Invalid status']);
            }

            // Send push notification
            dispatch(new JobNotification($user->fcmtoken, $title, $description, $data));

            // Save notification in database
            Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'seenByUser' => 0,
            ]);
        }

        return redirect()->back()->with([
            'status' => true, 
            'message' => 'Order status updated successfully'
        ]);
    }


   
    public function order(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = Auth::guard('user')->user();
            $userId = $user->id;
            $products = session('cart', []);
            $vehicle_color = session('vehicle_color', []);
            $vehicle_number = session('vehicle_number', []);
            $redeemedAmount = session('redeem_amount', []);
            $redeemedPoints = session('redeem_points', []);
            $dateTime = session('time', []);
            $startTime = session('start_time', []);
            $tip_amount = session('tip_amount', []);
            $orderTotal = session('orderTotal', []);
            $deliveryCharge = session('delivery_charge', 0);
            // return $redeemedAmount;
            $total = 0;
            foreach ($products as $id => $details) {
                $branchId = $details['branch_id'];
            }

            // ✅ CREATE ORDER WITHOUT PAYMENT GATEWAY
            $order = new Order();
            $order->code = random_int(10000000, 99999999);
            $order->user_id = $userId;
            $order->vehicle_color = $vehicle_color ?: 'NULL';
            $order->vehicle_number = $vehicle_number ?: 'NULL';
            $order->redeemed = $redeemedAmount ?: 0;
            $order->redeemed_points = $redeemedPoints ?: 0;
            $order->status = 'Pending';
            $order->payment = 'offline'; // ✅ manual payment
            $order->date = $dateTime['date'] ?? null;
            $order->time = $dateTime['time'] ?? $startTime;

            foreach ($products as $id => $details) {
                $total += floatval($details['price']) * floatval($details['quantity']);
            }

            $branch = Branch::find($branchId);
            $tax = $branch && $branch->status == 1 ? $branch->tax : 0;
            $order->total_amount = $total;
            $order->delivery_charge = $deliveryCharge;
            $order->save();

            $orderId = $order->id;

            // ✅ Save order items and toppings
            foreach ($products as $id => $details) {
                $orderItem = new OrderItem();
                $orderItem->order_id = $orderId;
                $orderItem->product_id = $details['product_id'];
                $orderItem->product_complementary_id = $details['complementary']['id'] ?? null;
                $orderItem->product_size = $details['size'] ?? 'NULL';
                $orderItem->product_price = $details['price'];
                $orderItem->branch_id = $details['branch_id'];
                $orderItem->product_name = $details['name'];
                $orderItem->quantity = $details['quantity'];
                $orderItem->tip = is_array($tip_amount) ? array_sum($tip_amount) : ($tip_amount ?: 0);
                $orderItem->sub_total = floatval($details['price']) * floatval($details['quantity']);
                $orderItem->delivery_status = $details['delivery_status'] ?? null;
                $orderItem->delivery_address = $details['delivery_address'] ?? null;
                
                $orderItem->save();

                if (isset($details['toppings_by_category'])) {
                    foreach ($details['toppings_by_category'] as $categoryId => $toppingIds) {
                        foreach ($toppingIds as $toppingId) {
                            $orderItemTopping = new OrderItemToppings();
                            $orderItemTopping->order_item_id = $orderItem->id;
                            $orderItemTopping->topping_id = $toppingId;
                            $orderItemTopping->category_id = $categoryId;
                            $orderItemTopping->save();

                            $topping = Topping::find($toppingId);
                            if ($topping) {
                                $total += $topping->price;
                            }
                        }
                    }
                }
            }

            $order->total_amount = $total + ($tip_amount ?: 0) + $tax + $deliveryCharge - ($redeemedAmount ?: 0);
            // ✅ Extract delivery info from session cart
            $order->save();

            // ✅ Loyalty points logic
            $points = $order->total_amount;
            if ($user) {
                $existingPoints = $user->point;
                $totalPoints = floor($existingPoints + $points);
                $user->update(['point' => $totalPoints]);
            }

            $reward = Reward::where('user_id', $user->id)->first();
            if($reward) {
               $reward->update([
                    'rewards' =>$reward->rewards - $redeemedPoints ,
                    'redeemed' => $redeemedPoints + ($reward->redeemed ?? 0),
                ]);
            }

            // ✅ Email notification
            $orderCode = $order->code;
            // Mail::to($user->email)->send(new OrderConfirm($orderCode));

            // ✅ Clear session
            session()->forget('cart');
            session()->forget('tip_amount');
            session()->forget('redeem_points');
            session()->forget('redeem_amount');
            session()->forget('vehicle_color');
            session()->forget('vehicle_number');
            session()->forget('time');
            session()->forget('start_time'); 
            session()->forget('delivery_charge');
            DB::commit();
            return redirect()->route('my-order')->with(['status' => true, 'message' => 'Order placed successfully! Payment will be handled manually.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

  public function stripePayment()
{
    $finalTotal = session('orderTotal');

    $gatewayFee = ($finalTotal * 0.025) + 0.25;

    $gatewayFee = round($gatewayFee, 2);
    $finalTotalWithFee = round($finalTotal + $gatewayFee, 2);
    

    Stripe::setApiKey(config('services.stripe.secret'));

    $session = StripeSession::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'gbp',
                'product_data' => [
                    'name' => 'Order Payment',
                ],
                'unit_amount' => (int) round($finalTotalWithFee * 100), // cents
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => route('stripe.cancel'),
    ]);

    return redirect($session->url);
}
public function stripeSuccess()
{
    $sessionId = request()->get('session_id');

    if (!$sessionId) {
        return redirect()->route('checkout')->with('error', 'Invalid payment');
    }

    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    $session = \Stripe\Checkout\Session::retrieve($sessionId);

    // ❌ stop if not paid
    if ($session->payment_status !== 'paid') {
        return redirect()->route('checkout')->with('error', 'Payment not completed');
    }
     DB::beginTransaction();
        try {
            $user = Auth::guard('user')->user();
            $userId = $user->id;
            $products = session('cart', []);
            $vehicle_color = session('vehicle_color', []);
            $vehicle_number = session('vehicle_number', []);
            $redeemedAmount = session('redeem_amount', []);
            $redeemedPoints = session('redeem_points', []);
            $dateTime = session('time', []);
            $startTime = session('start_time', []);
            $tip_amount = session('tip_amount', []);
            $orderTotal = session('orderTotal', []);
            $deliveryCharge = session('delivery_charge', 0);
            // return $redeemedAmount;
            $total = 0;
            foreach ($products as $id => $details) {
                $branchId = $details['branch_id'];
            }

            // ✅ CREATE ORDER WITHOUT PAYMENT GATEWAY
            $order = new Order();
            $order->code = random_int(10000000, 99999999);
            $order->user_id = $userId;
            $order->vehicle_color = $vehicle_color ?: 'NULL';
            $order->vehicle_number = $vehicle_number ?: 'NULL';
            $order->redeemed = $redeemedAmount ?: 0;
            $order->redeemed_points = $redeemedPoints ?: 0;
            $order->status = 'Pending';
            $order->payment = 'stripe'; // ✅ manual payment
            $order->date = $dateTime['date'] ?? null;
            $order->time = $dateTime['time'] ?? $startTime;

            foreach ($products as $id => $details) {
                $total += floatval($details['price']) * floatval($details['quantity']);
            }

            $branch = Branch::find($branchId);
            $tax = $branch && $branch->status == 1 ? $branch->tax : 0;
            $order->total_amount = $total;
            $order->delivery_charge = $deliveryCharge;
            $order->save();

            $orderId = $order->id;

            // ✅ Save order items and toppings
            foreach ($products as $id => $details) {
                $orderItem = new OrderItem();
                $orderItem->order_id = $orderId;
                $orderItem->product_id = $details['product_id'];
                $orderItem->product_complementary_id = $details['complementary']['id'] ?? null;
                $orderItem->product_size = $details['size'] ?? 'NULL';
                $orderItem->product_price = $details['price'];
                $orderItem->branch_id = $details['branch_id'];
                $orderItem->product_name = $details['name'];
                $orderItem->quantity = $details['quantity'];
                $orderItem->tip = is_array($tip_amount) ? array_sum($tip_amount) : ($tip_amount ?: 0);
                $orderItem->sub_total = floatval($details['price']) * floatval($details['quantity']);
                $orderItem->delivery_status = $details['delivery_status'] ?? null;
                $orderItem->delivery_address = $details['delivery_address'] ?? null;

                $orderItem->save();

                if (isset($details['toppings_by_category'])) {
                    foreach ($details['toppings_by_category'] as $categoryId => $toppingIds) {
                        foreach ($toppingIds as $toppingId) {
                            $orderItemTopping = new OrderItemToppings();
                            $orderItemTopping->order_item_id = $orderItem->id;
                            $orderItemTopping->topping_id = $toppingId;
                            $orderItemTopping->category_id = $categoryId;
                            $orderItemTopping->save();

                            $topping = Topping::find($toppingId);
                            if ($topping) {
                                $total += $topping->price;
                            }
                        }
                    }
                }
            }

            $finalTotal = $total + ($tip_amount ?: 0) + $tax + $deliveryCharge - ($redeemedAmount ?: 0);
            $gatewayFee = ($finalTotal * 0.025) + 0.25;

            $order->total_amount = $finalTotal + $gatewayFee;
            $order->gateway_fee = $gatewayFee;
            // ✅ Extract delivery info from session cart
            $order->save();

            // ✅ Loyalty points logic
            $points = $order->total_amount;
            if ($user) {
                $existingPoints = $user->point;
                $totalPoints = floor($existingPoints + $points);
                $user->update(['point' => $totalPoints]);
            }

            $reward = Reward::where('user_id', $user->id)->first();
            if($reward) {
               $reward->update([
                    'rewards' =>$reward->rewards - $redeemedPoints ,
                    'redeemed' => $redeemedPoints + ($reward->redeemed ?? 0),
                ]);
            }

            // ✅ Email notification
            $orderCode = $order->code;
            // Mail::to($user->email)->send(new OrderConfirm($orderCode));

            // ✅ Clear session
            session()->forget('cart');
            session()->forget('tip_amount');
            session()->forget('redeem_points');
            session()->forget('redeem_amount');
            session()->forget('vehicle_color');
            session()->forget('vehicle_number');
            session()->forget('time');
            session()->forget('start_time'); 
            session()->forget('delivery_charge');
            DB::commit();
            return redirect()->route('my-order')->with(['status' => true, 'message' => 'Order placed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
}

    private function getAccessToken($branchId)
    {
        $accessTokens = [
            7 => 'EAAAlhmrLWxke5X0wF3NnZvofLP5cqKzaYuhDP0o9XwRkQ3sy1wBfdu8BCEP7hbT',
            6 => 'EAAAljgj_zgkKeCYGCHJ5lnAIKLR_X3kfT5pXxHREQSZNARu-5O3K1qRLfAr1i9e',
            8 => 'EAAAlo_Lee_l3Du915VXyW9fQGm9N99wLKfuRQFn9QzdTLOnuh2MsMEhP0sL2hi4',
        ];
        return $accessTokens[$branchId] ?? 'EAAAlt7VHkCQ7YGtAJyDwAw1Of0nnrIvF5JwU8AyTuf_YA1Y8pJbEXbwqMSyfFBs';
    }
    public function markAllAsRead(Request $request)
    {
        $notification = Order::find($request->id);

        if ($notification) {
            $notification->seen = 1;
            $notification->save();
        }
    }
}
