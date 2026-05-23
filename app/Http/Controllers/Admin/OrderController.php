<?php

namespace App\Http\Controllers\admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Reward;
use App\Models\Notification;
use App\Models\RewardHistory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\JobNotification;
use App\Models\OrderComplationReward;
use App\Models\OrderCompletionRecord;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index() 
    {
        $orders= Order::with(['orderItem.complementaryProduct','orderItem.orderToppings.category', 'orderItem.orderToppings.toppings', 'user', 'orderItem.branch'])->latest()->get();
        // return $orders;
        return view('admin.orders.index', compact('orders'));
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
                    } else {
                        $title = '✅ Order Delivered!';
                        $description = "Your order #{$order->code} has been delivered successfully.";
                        $data['status'] = 'order_delivered';
                    }
                    $data['screen_name'] = 'MyOrders'; 
                    break;

                case 'Out for Delivery':
                    $title = '🚚 Order Out for Delivery!';
                    $description = "Your order #{$order->code} is out for delivery.";
                    $data['status'] = 'out_for_delivery';
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
}
