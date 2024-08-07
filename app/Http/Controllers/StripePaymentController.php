<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Stripe;
use Session;
use App\Models\PaymentDetail;
use App\Models\Merchant;

class StripePaymentController extends Controller
{
    public function stripeCheckoutPage(Request $request)
    {
        return redirect('/stripe/process'.'/'.base64_encode($request->curr).'/'.base64_encode($request->price).'/'.base64_encode($request->customer_name).'/'.base64_encode($request->customer_email).'/'.base64_encode($request->customer_phone).'/'.base64_encode($request->card_number).'/'.base64_encode($request->expiration).'/'.base64_encode($request->cvv).'/'.base64_encode($request->merchant_code).'/'.base64_encode($request->transaction_id));  
    }

    
    public function stripeProcess( $currency, $amount, $customer_name, $customer_email, $customer_phone, $card_number, $expiration, $cvv, $merchant_code, $transaction_id)
    {
        $exitMerchant = Merchant::where('merchant_code', base64_decode($merchant_code))->where('status', 'Enable')->first();
        if(empty($exitMerchant)){
            return "Invalid merchant";
        }
        $res['currency'] = base64_decode($currency);
        $res['amount'] = base64_decode($amount);
        $res['customer_name'] = base64_decode($customer_name); // Customer Name
        $res['customer_email'] = base64_decode($customer_email);
        $res['customer_phone'] = base64_decode($customer_phone);
        $res['card_number'] = base64_decode($card_number);
        $res['expiration'] = base64_decode($expiration);
        $res['cvv'] = base64_decode($cvv);
        $res['merchant_code'] = base64_decode($merchant_code);
        $res['transaction_id'] = base64_decode($transaction_id);
        // print_r($res); die;
         return view('payment-form.stripe-page', compact('res'));
    }

    public function stripeCheckoutForm(Request $request)
    {

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        $customer = \Stripe\Customer::create(array(
          'name' => $request->customer_name,
          'description' => 'test for pay2rax description',
          'email' => $request->email,
          'source' => $request->input('stripeToken'),
          "address" => ["city" => $request->city, "country" => $request->country, "line1" => $request->address, "postal_code" => $request->zip, "state" => $request->state]

      ));

        try {
            $data = \Stripe\Charge::create ( array (
                    "amount" => $request->amount.'00',
                    "currency" => $request->currency,
                    "customer" =>  $customer["id"],
                    "description" => $request->description
            ) );

            if($data->status == 'succeeded' || $data->status == 'success'){
                $payment_status ='success';
                date_default_timezone_set('Asia/Phnom_Penh');
                $created_date=date("Y-m-d H:i:s");
                list($exp_month, $exp_year) = explode("/", $request->expiration);
                
                PaymentDetail::create([
                    'merchant_code' => $request->merchant_code,
                    'transaction_id' => $data->id,
                    'fourth_party_transection' => $request->transaction_id,
                    'customer_name' => $request->customer_name,
                    'callback_url' => $data->receipt_url,
                    'amount' => $request->amount,
                    'payment_method' => "card",
                    'Currency' => $request->currency,
                    'ErrDesc' => 'Stripe Gateway',
                    'response_data' => $data,
                    'created_at' => $created_date,
                    'customer_id' => $data->customer,
                    'payment_status' => $payment_status,
                ]);
                // Code for Inser data into DB END

                echo "Transaction Information as follows" . '<br/>' .
                        "TransactionId : " . $request->transaction_id . '<br/>' .
                        "Currency : " . $request->currency . '<br/>' .
                        "Amount : " . $request->amount . '<br/>' .
                        "Datetime : " . $created_date . '<br/>' .
                        "Status : " . $payment_status;
                    die;
                   
            }
            
            
        } catch ( \Stripe\Error\Card $e ) {
            return 'failed';
        }

    }



}
