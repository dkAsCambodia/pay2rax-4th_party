<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Omnipay\Omnipay;
use App\Models\PaymentDetail;
use App\Models\Merchant;
use Session;

class PaypalPaymentController extends Controller
{
    private $gateway;
     function __construct()
    {
    	$this->gateway = Omnipay::create('PayPal_Rest');
    	$this->gateway->setClientId(env('PAYPAL_CLIENT_ID'));
    	$this->gateway->setSecret(env('PAYPAL_CLIENT_SECRET'));
    	$this->gateway->setTestMode(true);
    }

    public function paypalCheckout(Request $request)
    { 
        $exitMerchant = Merchant::where('merchant_code', $request->merchant_code)->where('status', 'Enable')->first();
        if(empty($exitMerchant)){
            return "Invalid merchant";
        }
        // $res['currency'] = $request->curr;
        // $res['amount'] = $request->price;
        // $res['customer_name'] = $request->customer_name;
        // $res['customer_email'] = $request->customer_email;  
        // $res['customer_phone'] = $request->customer_phone; 
        // print_r($res); die;
        try {
    		$response = $this->gateway->purchase(array(
    			'amount' => $request->price,
    			'currency' => $request->curr,
    			'returnUrl' => route('paypalCheckout.success'),
    			'cancelUrl' => route('paypalCheckout.cancel'),
    		))->send();
            $data = $response->getData();
            //    echo "<pre>";  print_r($data); die;
    		if ($response->isRedirect() && !empty($request->merchant_code)) {
                 // Code for Inser data into DB START
                 PaymentDetail::create([
                    'merchant_code' => $request->merchant_code,
                    'transaction_id' => $data['id'],
                    'fourth_party_transection' => $request->payin_request_id ?? $request->transaction_id,
                    'customer_name' => $request->customer_name,
                    'callback_url' => $data['links']['1']['href'],
                    'amount' => $request->price,
                    'payment_method' => "card",
                    'customer_id' => ! empty($request->customer_id) ? $request->customer_id : 0,
                    'Currency' => $request->curr,
                    'ErrDesc' => 'Paypal Gateway',
                ]);
                // Code for Inser data into DB END
    			$response->redirect();
    		}else{
    			return $response->getMessage();
    		}
    		
    	} catch (Exception $e) {
    		return $this->getMessage();
    	}
    }

    public function paypalSuccess(Request $request)
    {
        if ($request->input('paymentId') && $request->input('PayerID')) {
            $transaction = $this->gateway->completePurchase(array(
                'payer_id' => $request->input('PayerID'),
                'transactionReference' => $request->input('paymentId')
            ));
            $response = $transaction->send();
            if ($response->isSuccessful()) {
                $arr = $response->getData();
                
                date_default_timezone_set('Asia/Phnom_Penh');
                $created_date=date("Y-m-d H:i:s");
                PaymentDetail::where('transaction_id', $arr['id'])
                ->update([
                    'response_data' => json_encode($arr, true),
                    'created_at' => $created_date,
                    'customer_id' => $arr['payer']['payer_info']['payer_id'],
                    'payment_status' => 'success',
                ]);
                // echo "<pre>"; print_r($arr); die;
                
                echo "Transaction Information as follows" . '<br/>' .
                        "TransactionId : " . $arr['id'] . '<br/>' .
                        "Currency : " . $arr['transactions'][0]['amount']['currency'] . '<br/>' .
                        "Amount : " . $arr['transactions'][0]['amount']['total'] . '<br/>' .
                        "Datetime : " . $created_date . '<br/>' .
                        "Status : " . 'Success';
                    die;
            
            }
            else{
                return $response->getMessage();
            }
        }
        else{
            return 'Payment declined!!';
        }
    }

    public function paypalCancel(Request $request)
    {
        echo "Transaction Declined !" . '<br/>' .
             "Status : " . 'Cancel';
                    die;
        
    }


}
