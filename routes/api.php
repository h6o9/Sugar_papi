<?php

use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\AddToCartController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PlaceOrderController;
use App\Http\Controllers\Api\ProductDetailsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */


// Route::group(['namespace' => 'Api'], function () {
//     Route::post('register', 'AuthController@register');
//     Route::post('login', 'AuthController@login');
//     Route::get('notification', 'AuthController@notification');
//     Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//         return $request->user();
//     });

// });

Route::post('/register-user', [AuthController::class, 'register']);
Route::post('/register-verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/user-social-login', [AuthController::class, 'socialLogin']);
Route::post('/place-order', [PlaceOrderController::class, 'placeOrder']);
Route::post('/login-user', [AuthController::class, 'Login']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/user-forget-password', [AuthController::class, 'forgetPassword']);
Route::post('/user-verify-forget-password', [AuthController::class, 'verifyForgetOtp']);
Route::post('/user-reset-password', [AuthController::class, 'resetPassword']);
Route::middleware('auth:sanctum')->group(function () {
Route::get('/user-get-profile', [AuthController::class, 'getProfile']);
	Route::post('/user-update-profile', [AuthController::class, 'updateProfile']);
		Route::post('/user-change-password', [AuthController::class, 'ChangePassword']);
	Route::post('/verify-update-profile-otp', [AuthController::class, 'verifyUpdateprofileOtp']);
	Route::get('/user-login-info', [AuthController::class, 'getLoggedInUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
	Route::get('/home-products', [HomeController::class, 'homeProducts']);
	Route::get('/menu-items', [HomeController::class, 'Menueitems']);
	Route::get('/toppings', [HomeController::class, 'toppings']);
	Route::get('/my-orders-status', [OrderController::class, 'myOrders']);

Route::get('/product-details/{id}', [ProductDetailsController::class, 'getProductDetails']);
Route::post('/product-add-to-cart', [AddToCartController::class, 'addToCart']);
Route::get('/get-user-cart-items', [AddToCartController::class, 'getUserCartItems']);
Route::post('/cart-update-quantity/{id}', [AddToCartController::class, 'updateCartItemQuantity']);
Route::post('/delete-cart-item/{id}', [AddToCartController::class, 'deleteCartItem']);
Route::post('/continue-to-payments', [AddToCartController::class, 'proceedToPayment']);
Route::post('/place-order', [PlaceOrderController::class, 'placeOrder']);
Route::get('/get-branch-info', [AddToCartController::class, 'getBranchInfo']);

// filter data 

Route::get('/filter-data', [FilterController::class, 'filterData']);
Route::get('/terms-and-conditions', [PageController::class, 'termsAndConditions']);
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy']);
Route::get('/faq', [PageController::class, 'faq']);
Route::get('/gallery', [PageController::class, 'getGalleryImages']);
Route::get('/get-user-reward-amount', [PageController::class, 'getUserRewardAmount']);

//NOTIFICATION ROUTES
// Notifications
Route::get('/notifications', [NotificationController::class, 'getUserNotifications']);
Route::get('/notification/{id}', [NotificationController::class, 'showNotification']);
Route::post('/clearnotification', [NotificationController::class, 'clearAll']);
Route::post('/notifications-seen', [NotificationController::class, 'seenNotification'])
    ->name('notifications.seen');

	// 
	Route::get('/referral/validate/{code}', [ReferralController::class, 'validateReferralCode']);

// Register new user with referral code
Route::post('/register-with-referral', [ReferralController::class, 'registerWithReferral']);
 Route::post('/referral/generate', [ReferralController::class, 'generateLink']);
    
    // Get my referral link and points
    Route::get('/referral/my-link', [ReferralController::class, 'getMyLink']);
    
    // Get complete statistics
    Route::get('/referral/stats', [ReferralController::class, 'getStats']);
    
    // Use/redeem points
    Route::post('/referral/use-points', [ReferralController::class, 'usePoints']);
// get reward history
Route::get('/reward-history', [\App\Http\Controllers\Api\RewardHistoryController::class, 'index']);

});

// Webview Payment Routes for App
Route::get('/payment/stripe/webview/success', [\App\Http\Controllers\Api\PaymentController::class, 'stripeWebviewSuccess'])->name('api.payment.stripe.webview.success');
Route::get('/payment/stripe/webview/cancel', [\App\Http\Controllers\Api\PaymentController::class, 'stripeWebviewCancel'])->name('api.payment.stripe.webview.cancel');
// checkout_token from place-order (payment first, then order on success)
Route::get('/payment/stripe/webview/{checkout_token}', [\App\Http\Controllers\Api\PaymentController::class, 'stripeWebviewCheckout'])->name('api.payment.stripe.webview.checkout');
