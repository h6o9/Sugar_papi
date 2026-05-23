<?php

namespace App\Http\Controllers\Api;

use App\Models\Menu;
use App\Models\Product;
use App\Models\Topping;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    //

// public function homeProducts(Request $request)
// {
//     $request->validate([
//         'menu_id' => 'required|string', // number or 'all'
//     ]);

//     $menuId = $request->menu_id;

//     // CASE: All products
//     if ($menuId === 'all') {
//         $products = Product::where('status', '1')->get();
//     } elseif($menuId === "featured"){
//         $products = Product::where('is_featured', '1')->get();
//     } else {
//         $products = Product::where('menu_id', $menuId)
//                            ->where('status', '1')
//                            ->get();
//     }

//     // Prepare response with enhanced product information
//     $response = $products->map(function($product) {
//         $menu = $product->menu; // relation
        
//         // Calculate discount information
//         $discountInfo = $this->calculateDiscountInfo($product);
        
//         // Prepare base product data
//         $productData = [
//             'menu_name'        => $menu ? $menu->name : null,
//             'product_name'     => $product->name,
// 			'product_id'       => $product->id,
//             'price'            => $product->price,
//             'original_price'   => $product->original_price,
//             'image'            => $product->image,
//             'is_featured'      => (bool)$product->is_featured,
//         ];
        
//         // Add discount information if available
// 		if ($discountInfo['has_discount']) {

// 			// Add featured_method inside discount array
// 			$discountInfo['featured_method'] = $product->featured_method;

// 			$productData['discount'] = $discountInfo;
			
// 			// Add special highlight flag for products with discounts or featured items
// 			$productData['is_special'] = true;
// 			$productData['special_type'] = 'discount';
// 		}
        
//         // Add featured method information if available
//         if ($product->is_featured && $product->featured_method) {
//             $productData['featured_info'] = [
//                 'method' => $product->featured_method,
//                 'action' => $product->featured_action,
//                 'amount' => $product->featured_amount,
//                 'display_text' => $this->getFeaturedDisplayText($product)
//             ];
            
//             // Add special highlight flag for featured items
//             if (!isset($productData['is_special'])) {
//                 $productData['is_special'] = true;
//                 $productData['special_type'] = 'featured';
//             } else {
//                 $productData['special_type'] = 'both'; // Both discount and featured
//             }
//         }
        
//         return $productData;
//     });

//     return response()->json([
//         'status' => true,
//         'data'   => $response
//     ]);
// }

public function homeProducts(Request $request)
{
    $request->validate([
        'menu_id' => 'required|string', // number or 'all'
    ]);

    $menuId = $request->menu_id;

    /*
    |--------------------------------------------------------------------------
    | GET PRODUCTS
    |--------------------------------------------------------------------------
    */
    if ($menuId === 'all') {

        $products = Product::with([
                'menu',
                'complementaryProduct.complementary'
            ])
            ->where('status', '1')
            ->get();

    } elseif ($menuId === 'featured') {

        $products = Product::with([
                'menu',
                'complementaryProduct.complementary'
            ])
            ->where('is_featured', '1')
            ->where('status', '1')
            ->get();

    } else {

        $products = Product::with([
                'menu',
                'complementaryProduct.complementary'
            ])
            ->where('menu_id', $menuId)
            ->where('status', '1')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | GET FIRST VARIANT OF EACH PRODUCT
    |--------------------------------------------------------------------------
    | ProductVariant table mein agar product_id exist karta hai,
    | to us variant ki price aur original_price use hogi.
    */
    $productIds = $products->pluck('id')->toArray();

    $variants = ProductVariant::whereIn('product_id', $productIds)
        ->orderBy('id', 'asc')
        ->get()
        ->groupBy('product_id');

    /*
    |--------------------------------------------------------------------------
    | PREPARE RESPONSE
    |--------------------------------------------------------------------------
    */
    $response = $products->map(function ($product) use ($variants) {

        $menu = $product->menu;

        /*
        |--------------------------------------------------------------------------
        | CHECK PRODUCT VARIANT
        |--------------------------------------------------------------------------
        | Agar variant exist karta hai to uska price use karo,
        | warna original product ka price use karo.
        */
        $variant = null;

        if (isset($variants[$product->id])) {
            $variant = $variants[$product->id]->first(); // first variant
        }

        $finalPrice = $variant && !empty($variant->price)
            ? $variant->price
            : $product->price;

        $finalOriginalPrice = $variant && !empty($variant->original_price)
            ? $variant->original_price
            : $product->original_price;

        // Calculate discount information using updated prices
        $discountInfo = $this->calculateDiscountInfo($product);

        // Override prices in discount calculation if variant exists
        if ($variant) {
            $discountInfo['price'] = $finalPrice;
            $discountInfo['original_price'] = $finalOriginalPrice;
        }

        $productData = [
            'menu_name'      => $menu ? $menu->name : null,
            'product_name'   => $product->name,
            'product_id'     => $product->id,
            'price'          => $finalPrice,
            'original_price' => $finalOriginalPrice,
            'image'          => $product->image,
            'is_featured'    => (bool) $product->is_featured,
        ];

        /*
        |--------------------------------------------------------------------------
        | VARIANT INFORMATION (OPTIONAL)
        |--------------------------------------------------------------------------
        */
        if ($variant) {
            $productData['variant'] = [
                'id'             => $variant->id,
                'size'           => $variant->size,
                'price'          => $variant->price,
                'original_price' => $variant->original_price,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | COMPLEMENTARY PRODUCT
        |--------------------------------------------------------------------------
        */
        $comp = $product->complementaryProduct->first();

        if ($comp && $comp->complementary) {
            $productData['complementary_product'] = [
                'id'    => $comp->complementary->id,
                'name'  => $comp->complementary->name,
                'image' => $comp->complementary->image,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | DISCOUNT LOGIC
        |--------------------------------------------------------------------------
        */
        if ($discountInfo['has_discount']) {

            $discountInfo['featured_method'] = $product->featured_method;

            $productData['discount'] = $discountInfo;

            $productData['is_special'] = true;
            $productData['special_type'] = 'discount';
        }

        /*
        |--------------------------------------------------------------------------
        | FEATURED LOGIC
        |--------------------------------------------------------------------------
        */
        if ($product->is_featured && $product->featured_method) {

            $productData['featured_info'] = [
                'method'       => $product->featured_method,
                'action'       => $product->featured_action,
                'amount'       => $product->featured_amount,
                'display_text' => $this->getFeaturedDisplayText($product),
            ];

            if (!isset($productData['is_special'])) {
                $productData['is_special'] = true;
                $productData['special_type'] = 'featured';
            } else {
                $productData['special_type'] = 'both';
            }
        }

        return $productData;
    });

    return response()->json([
        'status'  => 200,
        'message' => 'Products retrieved successfully',
        'data'    => $response,
    ], 200);
}
/**
 * Calculate discount information for a product
 */
private function calculateDiscountInfo($product)
{
    $hasDiscount = false;
    $discountPercentage = null;
    $discountAmount = null;
    $savedAmount = null;
    
    // Check if product has original price and current price
    if ($product->original_price && $product->price && $product->original_price > $product->price) {
        $hasDiscount = true;
        
        // Calculate original price as float
        $originalPrice = floatval($product->original_price);
        $currentPrice = floatval($product->price);
        
        // Calculate discount amount
        $discountAmount = $originalPrice - $currentPrice;
        
        // Calculate discount percentage
        if ($originalPrice > 0) {
            $discountPercentage = round(($discountAmount / $originalPrice) * 100, 2);
        }
        
        $savedAmount = number_format($discountAmount, 2);
    }
    
    return [
        'has_discount' => $hasDiscount,
        'discount_percentage' => $discountPercentage,
        'discount_amount' => $discountAmount ? number_format($discountAmount, 2) : null,
        'saved_amount' => $savedAmount,
        'original_price_formatted' => $product->original_price ? number_format(floatval($product->original_price), 2) : null,
        'current_price_formatted' => $product->price ? number_format(floatval($product->price), 2) : null,
    ];
}

/**
 * Get display text for featured method
 */
private function getFeaturedDisplayText($product)
{
    if (!$product->featured_method || !$product->featured_amount) {
        return null;
    }
    
    $amount = $product->featured_amount;
    
    switch ($product->featured_method) {
        case 'percentage':
            return $amount . '% OFF';
        case 'fixed':
            return 'Rs. ' . $amount . ' OFF';
        case 'buyonegetone':
            return 'Buy 1 Get 1 Free';
        case 'combo':
            return 'Combo Offer: ' . $amount;
        default:
            return $product->featured_method . ': ' . $amount;
    }
}

// public function homeProducts(Request $request)
// {
//     $request->validate([
//         'menu_id' => 'required|string',
//     ]);

//     $menuId = $request->menu_id;

//     // CASE: All products
//     if ($menuId === 'all') {

//         $products = Product::where('status', '1')
//             ->whereIn('rule', ['bulk','Priority'])
//             ->where('featured_action', 'decrease')
//             ->get();

//     } elseif ($menuId === "featured") {

//         $products = Product::where('is_featured', '1')
//             ->whereIn('rule', ['bulk','Priority'])
//             ->where('featured_action', 'decrease')
//             ->get();

//     } else {

//         $products = Product::where('menu_id', $menuId)
//             ->whereIn('rule', ['bulk','Priority'])
//             ->where('featured_action', 'decrease')
//             ->get();
//     }

//     // Prepare response (same as before + condition fields)
//     $response = $products->map(function ($product) {

//         $menu = $product->menu;

//         $data = [
//             'menu_name'    => $menu ? $menu->name : null,
//             'product_name' => $product->name,
//             'price'        => $product->price,
//             'image'        => $product->image,
//         ];

//         // ✅ Only when rule applied
//         if ($product->featured_action === 'decrease') {

//             // percentage case
//             if ($product->featured_method === 'percentage') {
//                 $data['discount_percentage'] = $product->featured_amount;
//                 $data['original_price'] = $product->original_price;
//                 $data['current_price']  = $product->price;
//             }

//             // fixed amount case
//             if ($product->featured_method === 'fixed amount') {
//                 $data['original_price'] = $product->original_price;
//                 $data['current_price']  = $product->price;
//             }
//         }

//         return $data;
//     });

//     return response()->json([
//         'status' => true,
//         'data'   => $response
//     ]);
// }

	public function Menueitems() {
		$menueitems = Menu::get();
		
		return response()->json([
			'status' => true,
			'data'   => $menueitems
		]);
	}

	public function toppings() {
		$toppings = Topping::get();
		return response()->json([
			'status' => true,
			'data'   => $toppings
		]);
	}
}
