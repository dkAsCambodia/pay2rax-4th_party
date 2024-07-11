<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Stripe;
use Session;
use App\Models\PaymentDetail;

class StripePaymentController extends Controller
{
    public function stripeCheckoutPage(Request $request)
    {

        $res['merchant_code'] = $request->merchant_code;
        $res['currency'] = $request->curr;
        $res['amount'] = $request->price;
        $res['customer_name'] = $request->customer_name; // Customer Name
        $res['customer_email'] = $request->customer_email;
        $res['customer_phone'] = $request->customer_phone;
        $res['card_number'] =$request->card_number;
        $res['expiration'] =$request->expiration;
        $res['cvv'] =$request->cvv;

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
                // $userId = $this->getuserId($request->Fname, $request->lname, $request->email);
                // Code for Inser data into DB START
                // Transaction::create([
                //     'user_id' => $userId,
                //     'account_id' => $request->account_id,
                //     'cause_detail_id' => $request->cause_detail_id,
                //     'transaction_id' => $data->id,
                //     'main_transaction_id' => $data->id,
                //     'total_campaign' => '1',
                //     'total_amount' => $request->amount,
                //     'currency' => $request->currency,
                //     'currency_symbol' => $request->currencySymbol,
                //     'frequency' => $request->frequency,
                //     'response_all' => $data,
                //     'receipt_url' => $data->receipt_url,
                //     'gateway_name' => 'Stripe',
                //     'payment_timezone' => 'Asia/Bangkok',
                //     'payment_time' => $created_date,
                //     'message' => $request->description,
                //     'card_number' => $request->card_number,
                //     'cvv' => $request->cvv,
                //     'exp_month' => $exp_month,
                //     'exp_year' => $exp_year,
                //     'future_payment_custId' => $data->customer,
                //     'status' => $payment_status,
                // ]);
                PaymentDetail::create([
                    'merchant_code' => $request->merchant_code,
                    'transaction_id' => $data->id,
                    'fourth_party_transection' => $data->id,
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
                return $payment_status;
                   
            }
            
            
        } catch ( \Stripe\Error\Card $e ) {
            return 'failed';
        }

    }



}
