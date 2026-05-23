<?php

namespace App\Http\Controllers\Api;

use Log;
use Exception;
use App\Models\User;
use App\Models\Order;
use App\Models\Branch;
use App\Models\Reward;
use App\Models\OrderItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Jobs\JobNotification;
use App\Models\RewardHistory;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderComplationReward;
use App\Models\OrderCompletionRecord;

class OrderController extends Controller
{
    //

public function myOrders(Request $request)
{
    $user   = auth()->user();
    $status = $request->status;

    $orders = Order::where('user_id', $user->id)
        ->where('status', $status)
        ->latest()
        ->get();

    if ($orders->isEmpty()) {
        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => []
            ]
        ]);
    }

    $allItems = collect();

    foreach ($orders as $order) {

        $orderItems = OrderItem::where('order_id', $order->id)
            ->with([
                'product:id,name,image',
                'orderToppings' => function ($q) {
                    $q->with([
                        'category:id,name',
                        'toppings:id,name'
                    ]);
                }
            ])
            ->get()
            ->map(function ($item) {

                // =========================
                // TOPPINGS
                // =========================
                $toppings = $item->orderToppings->map(function ($t) {
                    return [
                        'category_name' => $t->category->name ?? null,
                        'topping_name'  => $t->toppings->name ?? null
                    ];
                });

                // =========================
                // COMPLEMENTARY PRODUCT (NEW)
                // =========================
                $complementaryProduct = null;

                if (!empty($item->product_complementary_id)) {

                    $comp = DB::table('products')
                        ->where('id', $item->product_complementary_id)
                        ->first();

                    if ($comp) {
                        $complementaryProduct = [
                            'id'    => $comp->id,
                            'name'  => $comp->name,
                            'image' => $comp->image,
                        ];
                    }
                }

                return [
                    'product_id'       => $item->product_id,
                    'product_name'     => $item->product_name,
                    'product_image'    => $item->product->image ?? null,
                    'product_size'     => $item->product_size,
                    'product_price'    => $item->product_price,
                    'product_original_price' => $item->original_price,
                    'delivery_address' => $item->delivery_address,
                    'order_type'       => $item->order_type,
                    'toppings'         => $toppings,

                    // ✅ NEW FIELD
                    'complementary_product' => $complementaryProduct,
                ];
            });

        $allItems = $allItems->merge($orderItems);
    }

    $order = $orders->first();

    if (in_array($status, ['Pending', 'Order Ready'])) {
        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $allItems
            ]
        ]);
    }

    if ($status === 'Delivered') {

        $branchId = $order?->orderItem?->first()?->branch_id;
        $branch   = $branchId ? Branch::find($branchId) : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'message'          => 'Your order has been placed successfully.',
                'total_amount'     => $order->total_amount ?? 0,
                'estimated_tax'    => $branch->tax ?? 0,
                "order_code"       => $order->code,
                'estimated_amount' => $order->estimated_total ?? 0,
                'order_type'       => $order?->orderItem?->first()?->order_type,
                'delivery_address' => $order?->orderItem?->first()?->delivery_address,
                'items'            => $allItems,
            ]
        ]);
    }

    return response()->json([
        'status' => 'success',
        'data' => [
            'items' => $allItems
        ]
    ]);
}






}
