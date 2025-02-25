<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentDetailController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentEcommController;
use App\Http\Controllers\PaymentMapController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PaypalPaymentController;
use App\Http\Controllers\BanksyPaymentController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\XprizoPaymentController;
use App\Http\Controllers\IpintPaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(PaymentDetailController::class)->group(function () {
    // Route::get('payment', 'paymentNewNew');
    // Route::get('payment', 'payment_new');
    // Route::post('payment', 'payment');
    Route::get('payment-submit', 'paymentSubmit');
    Route::get('ipay-response', 'showResponse');
    Route::post('payment-response', 'getPaymentResponse');
    // Route::post('ipay-response', 'showResponse');

    Route::post('demo-payment-form', 'demoPaymentForm');

    Route::post('test-callBackUrl', 'testCallBackUrl');
});

Route::controller(PaymentEcommController::class)->group(function () {
    Route::get('payment', 'paymentNewNew');
});

Route::controller(PaypalPaymentController::class)->group(function () {
    Route::post('paypal/checkout', 'paypalCheckout')->name('paypal.checkout');
    
});

Route::controller(BanksyPaymentController::class)->group(function () {
    Route::get('bnk/checkout', 'banksyCheckout')->name('apiroute.banksy.checkout');
    Route::post('/bnkWebhookNotifiication', 'bnkWebhookNotifiication'); 
    Route::post('depositResponse', 'depositResponse')->name('apiroute.depositResponse'); 
});

Route::controller(XprizoPaymentController::class)->group(function () {
    Route::get('xpz/deposit/', 'xpzDepositApifun')->name('apiroute.xpz.depositApi');
    Route::post('xpz/depositResponse', 'xpzDepositResponse')->name('apiroute.xpzDepositResponse'); 
    Route::post('/xpzWebhookNotifiication', 'xpzWebhookNotifiication'); 

    Route::get('xpz/withdrawal/', 'xpzwithdrawApifun')->name('apiroute.xpz.withdrawalApi');
    Route::post('xpz/withdrawalResponse', 'xpzWithdrawalResponse')->name('apiroute.xpzWithdrawalResponse'); 
});

Route::controller(IpintPaymentController::class)->group(function () {
    Route::get('ip/checkout', 'ipintCheckout')->name('apiroute.ipint.checkout');
    Route::post('ip/depositResponse', 'ipintdepositResponse')->name('apiroute.ipint.depositResponse'); 
    Route::post('/ipintDeposit/WebhookNotifiication', 'ipintDepositWebhookNotifiication'); 
});

Route::controller(StripePaymentController::class)->group(function () {
    Route::post('stripe/checkout', 'stripeCheckoutPage')->name('stripe.CheckoutPage');
    
});

Route::controller(MerchantController::class)->group(function () {
    Route::get('sow-payment-map/{merchant}', 'sowPaymentMapApi');
});

Route::controller(BillingController::class)->group(function () {
    Route::get('view-billing/{merchant}', 'viewBilling')->name('view/billing');
    Route::get('view-billing-agent/{agent}', 'viewBillingAgent')->name('view/billing-agent');
});

Route::controller(OrderController::class)->group(function () {
    Route::post('orders/create', 'create');
    Route::get('orders/paginate', 'paginate');
});

/*  Payment api for Oneshop  */
Route::controller(PaymentMapController::class)->group(function () {
    Route::match(['get', 'post'], 'get-payment-prices', 'getPaymentPrices');
    Route::match(['get', 'post'], 'get-payment-url', 'getPaymentPricesNew');
});

// ------------------------------ Gtech DK START ---------------------------------//
Route::controller(PayoutController::class)->group(function () {
    Route::get('gpayout', 'payoutRequest');
    // Route::get('api_payout_status', 'api_payout_status');     //for webhook callback
});
// ------------------------------ Gtech DK END ---------------------------------//