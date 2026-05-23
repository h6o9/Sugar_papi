<?php

namespace App\Http\Controllers\Home;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Topping;
use App\Models\Category;
use App\Models\Reward;
use App\Models\RewardSetting;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use App\Models\UserTimeSlotes;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;

class CartController extends Controller
{
    public function myCart()
    {
        $carts = session('cart');
        // return $carts;
        $branchess = Branch::all();
        $userId = Auth::guard('user')->id();
        $userTimeSlots = UserTimeSlotes::where('user_id', $userId)
            ->first();
        $timeSlots = TimeSlot::all();
        // return $carts;
        $loyaltyPoints = Reward::where('user_id', $userId)->first();
        $pricePerPoint = RewardSetting::first();
        // return $carts;
        $lastItem = collect($carts)->last();
        $distanceData = $this->applyDistanceCalculation($carts);
        // return $loyaltyPoints;
        return view('home.my-cart', compact('timeSlots', 'userTimeSlots', 'branchess','loyaltyPoints', 'carts','pricePerPoint','distanceData'));
    }
    Private function applyDistanceCalculation($cart)
    {
        $lastItem = collect($cart)->last();
        $deliveryStatus = $lastItem['delivery_status'] ?? null;
        $userLat = $lastItem['home_address_latitude'] ?? null;
        $userLng = $lastItem['home_address_longitude'] ?? null;
        // Branch location
        $branchLat = Branch::first()->latitude ?? 0;
        $branchLng = Branch::first()->longitude ?? 0;
        $deliveryCharge = 0;
        $distance = 0;
                if ($deliveryStatus == 2 && $userLat && $userLng) {
                    $distance = $this->getDistanceFromGoogle($branchLat, $branchLng, $userLat, $userLng);
                    // return $distance;
                    if ($distance <= 1) {
                        $deliveryCharge = 1.99;
                    } elseif ($distance <= 2) {
                        $deliveryCharge = 2.99;
                    } elseif ($distance <= 3) {
                        $deliveryCharge = 3.49;
                    } elseif ($distance <= 5) {
                        $deliveryCharge = 4.99;
                    } else {
                        $deliveryCharge = 5.99;
                    }
                }
                // session save
                Session::put('delivery_charge', $deliveryCharge);
                Session::put('distance', $distance);
                return [
                    'delivery_charge' => $deliveryCharge,
                    'distance_miles' => round($distance, 2),
                ];
    }
    Private function getDistanceFromGoogle($originLat, $originLng, $destLat, $destLng)
        {
            $apiKey = env('GOOGLE_MAPS_API_KEY');

            $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => $originLat . ',' . $originLng,
                'destinations' => $destLat . ',' . $destLng,
                'units' => 'imperial', // miles
                'key' => $apiKey
            ]);

            $data = $response->json();

            if (
                isset($data['rows'][0]['elements'][0]['distance']['value'])
            ) {
                // meters → miles
                $meters = $data['rows'][0]['elements'][0]['distance']['value'];
                $miles = $meters * 0.000621371;

                return $miles;
            }

            return 0;
        }
public function addToCart(Request $request)
{
    try {
        $product = Product::with('variants')->findOrFail($request->product_id);
        $complementryProduct = $request->filled('complementary_id')
            ? Product::where('id', $request->complementary_id)->first()
            : null;

        if ($request->branch_id) {
            $branch = Branch::where('id', $request->branch_id)->first();
            if (!$branch) return response()->json(['success' => false, 'message' => 'Branch not found.'], 404);
        } else {
            $branch = Branch::where('status', 1)->first();
            if (!$branch) return response()->json(['success' => false, 'message' => 'No active branch.'], 404);
        }

        $toppingsByCategory = $request->toppings_by_category ?? [];
        $cart = Session::get('cart', []);

        $size = '';
        if (!$request->variant_id) {
            $price = floatval(trim($product->price));
        } else {
            $variant = $product->variants->where('id', $request->variant_id)->first();
            $price = floatval(trim($variant->price));
            $size = $variant->size;
        }

        $cartKey = $request->product_id . '-' . ($request->variant_id ?? '');

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += (int)$request->quantity;
        } else {
            // ✅ Initialize cart item — NO premature session save, NO undefined variables
            $cart[$cartKey] = [
                "product_id"                  => $product->id,
                "variant_id"                  => (int)$request->variant_id,
                "name"                        => $product->name,
                "price"                       => $price,
                "size"                        => $size,
                "image"                       => $product->image,
                "branch_id"                   => $branch->id,
                "branch_name"                 => $branch->name,
                "quantity"                    => (int)$request->quantity,
                "delivery_status"             => $request->delivery_status ?? 1,
                "delivery_address"            => $request->delivery_address ?? '',
                "location"                    => $request->location ?? '',
                "toppings_by_category"        => [],   // ✅ filled below
                "toppingsName_by_categoryName" => [],  // ✅ filled below (was undefined!)
                "home_address_latitude"       => $request->lat,
                "home_address_longitude"      => $request->lng,
                "complementary"               => null,
            ];

            // ✅ Fill toppings BEFORE session save
            foreach ($toppingsByCategory as $toppingCategory) {
                $categoryId  = $toppingCategory['category_id'];
                $toppingIds  = $toppingCategory['toppings'];

                $existing = $cart[$cartKey]['toppings_by_category'][$categoryId] ?? [];
                $cart[$cartKey]['toppings_by_category'][$categoryId] = array_unique(array_merge($existing, $toppingIds));
            }

            foreach ($toppingsByCategory as $toppingCategory) {
                $categoryId   = $toppingCategory['category_id'];
                $toppingIds   = $toppingCategory['toppings'];
                $categoryName = Category::findOrFail($categoryId)->name;
                $toppingNames = Topping::whereIn('id', $toppingIds)->pluck('name')->toArray();

                $cart[$cartKey]['toppingsName_by_categoryName'][] = [
                    'category_name' => $categoryName,
                    'topping_names' => $toppingNames,
                ];
            }
        }

        // ✅ Handle complementary
        $cart[$cartKey]['complementary'] = $complementryProduct ? [
            'id'    => $complementryProduct->id,
            'name'  => $complementryProduct->name,
            'image' => $complementryProduct->image,
            'price' => 0,
        ] : null;

        // ✅ Save ONCE at the end
        Session::put('cart', $cart);

        return response()->json([
            'success' => true,
            // 'message' => 'Product added to cart successfully!',
            'data'    => count($cart),
            'cart'    => $cart,
        ]);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

    public function remove(Request $request)
    {
        if ($request->product_id) {
            $cart = session()->get('cart');

            if ($request->variant_id) {
                // Remove the product with the specific variant ID
                $key = $request->product_id . '-' . $request->variant_id;
                unset($cart[$key]);
            } else {
                // Remove the product without considering the variant
                foreach ($cart as $key => $item) {
                    if ($item['product_id'] == $request->product_id) {
                        unset($cart[$key]);
                    }
                }
            }

            session()->put('cart', $cart);

            $data = count((array) $cart);

            return response()->json([
                'success' => true,
                'message' => 'Product removed from the cart successfully!',
                'cart' => $cart,
                'data' => $data,
            ]);
        }
    }


    public function updateCart(Request $request)
    {
        $cart = session()->get('cart', []);
        // Validate input parameters
        if ($request->has(['quantity', 'product_id'])) {
            // Determine the key for the product in the cart
            if ($request->has('variant_id')) {
                $key = $request->product_id . '-' . $request->variant_id;
            }

            // Check if product exists in the cart
            if (isset($cart[$key])) {
                // Update the quantity
                $cart[$key]['quantity'] = $request->quantity;

                // Update session with modified cart
                session()->put('cart', $cart);

                // Get updated cart data
                $cart = session('cart', []);
                $data = count($cart);

                // Build response with updated cart and existing product details
                return response()->json([
                    'success' => true,
                    'message' => 'Quantity updated in the cart successfully!',
                    'cart' => $cart, // Contains updated quantity along with existing details
                    'data' => $data,
                    'product' => [ // Include details of the updated product
                        'product_id' => $request->product_id,
                        'name' => $cart[$key]['name'] ?? null,
                        'price' => $cart[$key]['price'] ?? null,
                        'image' => $cart[$key]['image'] ?? null,
                        'size' => $cart[$key]['size'] ?? null,
                    ],
                ]);
            } elseif ($request->has(['quantity', 'product_id'])) {
                $cart = session()->get('cart', []);
                // Check if product exists in the cart
                if (isset($cart[$request->product_id . '-'])) {
                    // Update the quantity only
                    $cart[$request->product_id . '-']['quantity'] = $request->quantity;

                    // Update session with modified cart
                    session()->put('cart', $cart);

                    // Get updated cart data
                    $cart = session('cart', []);
                    $data = count($cart);

                    // Build response with updated cart and existing product details
                    return response()->json([
                        'success' => true,
                        'message' => 'Quantity updated in the cart successfully!',
                        'cart' => $cart, // Contains updated quantity along with existing details
                        'data' => $data,
                        'product' => [ // Include details of the updated product
                            'product_id' => $request->product_id,
                            'name' => $cart[$request->product_id]['name'] ?? null, // Access name from existing cart data
                            'price' => $cart[$request->product_id]['price'] ?? null, // Access price from existing cart data
                            'image' => $cart[$request->product_id]['image'] ?? null, // Access image from existing cart data
                        ],
                    ]);
                } else {
                    // Product not found in the cart
                    return response()->json([
                        'success' => false,
                        'message' => 'Product with ID ' . $request->product_id . ' and variant ID ' . ($request->variant_id ?? 'N/A') . ' not found in your cart.',
                    ]);
                }
            } else {
                // Invalid request, handle missing inputs
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request. Please provide quantity and product_id.',
                ]);
            }
        }
    }
    public function updateMyCartValue(Request $request)
    {
        // Load existing cart data from session
        $cart = session()->get('cart', []);

        // Validate input parameters
        if ($request->has(['quantity', 'product_id'])) {
            // Determine the key for the product in the cart
            if ($request->has('variant_id')) {
                $key = $request->product_id . '-' . $request->variant_id;
            }
            // else {
            //     $key = $request->product_id;
            // }

            // Check if product exists in the cart
            if (isset($cart[$key])) {
                // Update the quantity
                $cart[$key]['quantity'] = $request->quantity;

                // Update session with modified cart
                session()->put('cart', $cart);

                // Get updated cart data
                $cart = session('cart', []);
                $data = count($cart);

                // Build response with updated cart and existing product details
                return response()->json([
                    'success' => true,
                    'message' => 'Quantity updated in the cart successfully!',
                    'cart' => $cart, // Contains updated quantity along with existing details
                    'data' => $data,
                    'product' => [ // Include details of the updated product
                        'product_id' => $request->product_id,
                        'name' => $cart[$key]['name'] ?? null,
                        'price' => $cart[$key]['price'] ?? null,
                        'image' => $cart[$key]['image'] ?? null,
                        'size' => $cart[$key]['size'] ?? null,
                    ],
                ]);
            } elseif ($request->has(['quantity', 'product_id'])) {
                $cart = session()->get('cart', []);

                // Check if product exists in the cart
                if (isset($cart[$request->product_id . '-'])) {
                    // Update the quantity only
                    $cart[$request->product_id . '-']['quantity'] = $request->quantity;
                    // Update session with modified cart
                    session()->put('cart', $cart);
                    // Get updated cart data
                    $cart = session('cart', []);
                    $data = count($cart);
                    // Build response with updated cart and existing product details
                    return response()->json([
                        'success' => true,
                        'message' => 'Quantity updated in the cart successfully!',
                        'cart' => $cart, // Contains updated quantity along with existing details
                        'data' => $data,
                        'product' => [ // Include details of the updated product
                            'product_id' => $request->product_id,
                            'name' => $cart[$request->product_id]['name'] ?? null, // Access name from existing cart data
                            'price' => $cart[$request->product_id]['price'] ?? null, // Access price from existing cart data
                            'image' => $cart[$request->product_id]['image'] ?? null, // Access image from existing cart data
                        ],
                    ]);
                } else {
                    // Product not found in the cart
                    return response()->json([
                        'success' => false,
                        'message' => 'Product with ID ' . $request->product_id . ' and variant ID ' . ($request->variant_id ?? 'N/A') . ' not found in your cart.',
                    ]);
                }
            } else {
                // Invalid request, handle missing inputs
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request. Please provide quantity and product_id.',
                ]);
            }
        }
    }
    public function updateTime(Request $request)
    {
        $userId = Auth::guard('user')->id();
        $date = $request->date_input;
        $time = $request->input('time-radio');
        $userTimeSlot = UserTimeSlotes::where('user_id', $userId)
            ->first();
        if ($userId) {
            if ($userTimeSlot) {
                // If the user already has a time slot, update the existing one
                $userTimeSlot->time = $time;
                $userTimeSlot->date = $date;
                $userTimeSlot->save();
            } else {
                // If the user doesn't have a time slot, create a new one
                UserTimeSlotes::create([
                    'user_id' => $userId,
                    'date' => $date, // Include the date in the creation if needed
                    'time' => $time,
                ]);
            }
        }
        $newArray = [
            'date' => $date,
            'time' => $time,
        ];

        session(['time' => $newArray]);
    }

    public function timeSlotes(Request $request)
    {
        $start_time = $request->selectedTime;
        session(['start_time' => $start_time]);
    }

    public function storeTipInSession(Request $request)
    {
        $tipAmount = $request->input('tipAmount');
        $redeemAmount = $request->input('redeemAmount');
        $redeemPoints = $request->input('redeemPoints');
        if (is_array($tipAmount)) {
            // If it's an array, you might want to handle it differently, such as summing the values
            $tipAmount = array_sum($tipAmount);
        }
        if (is_array($redeemAmount)) {
            // If it's an array, you might want to handle it differently, such as summing the values
            $redeemAmount = array_sum($redeemAmount);
        }
        session(['tip_amount' => $tipAmount]);
        session(['redeem_amount' => $redeemAmount]);
        session(['redeem_points' => $redeemPoints]);
    }

    public function storeVehicleInfo(Request $request)
    {
        $vehicleColor = $request->input('vehicle_color');
        $vehicleNumber = $request->input('vehicle_number');
        $redeemed = $request->input('redeemed');
        if ($vehicleColor && $vehicleNumber) {
            session(['vehicle_color' => $vehicleColor]);
            session(['vehicle_number' => $vehicleNumber]);
        }
        if ($redeemed) {
            session(['redeemed' => trim($redeemed)]);
        }
    }

	
// CartController.php mein naya method add karein
public function storeTipAndDelivery(Request $request)
{
    // Tip aur delivery dono ko session mein save karein
    session([
        'tip' => $request->tipAmount,
        'delivery_charges' => $request->deliveryAmount,
    ]);
    
    \Log::info('Tip and Delivery saved to session:', [
        'tip' => $request->tipAmount,
        'delivery' => $request->deliveryAmount
    ]);
    
    return response()->json(['success' => true, 'message' => 'Tip and delivery saved']);
}
}
