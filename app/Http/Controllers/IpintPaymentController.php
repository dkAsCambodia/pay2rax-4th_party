<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\GatewayAccount;
use App\Models\GatewayAccountMethod;
use App\Models\GatewayPaymentChannel;
use App\Models\Merchant;
use App\Models\ParameterSetting;
use App\Models\ParameterValue;
use App\Models\PaymentDetail;
use App\Models\SettleRequest;
use App\Models\PaymentMap;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Session;


class IpintPaymentController extends Controller
{
    public function ipintDepositform(Request $request)
    {
        return view('payment-form.ipint.ipint-form');
    }

    public function ipintCheckout(Request $request)
    {
        // echo "<pre>";  print_r($request->all()); die;
        $arrayData = [];
        $getGatewayParameters = [];
        $paymentMap = PaymentMap::where('id', $request->product_id)->first();
        if (! $paymentMap) {
            return 'product not exist';
        }
        if ($paymentMap->status == 'Disable') {
            return 'product is Disable';
        }
        $merchantData=Merchant::where('merchant_code', $request->merchant_code)->first();
        if (empty($merchantData)) {
            return 'Invalid Merchants!';
        }

        if ($paymentMap->channel_mode == 'single') {
            $gatewayPaymentChannel = GatewayPaymentChannel::where('id', $paymentMap->gateway_payment_channel_id)->first();
            if (! $gatewayPaymentChannel) {
                return 'gatewayPaymentChannel not exist';
            }
            if ($gatewayPaymentChannel->status == 'Disable') {
                return 'gatewayPaymentChannel is Disable';
            }
            $paymentMethod = PaymentMethod::where('id', $gatewayPaymentChannel->gateway_account_method_id)->first();
            $arrayData['method_name'] = $paymentMethod->method_name;
            if (! $paymentMethod) {
                return 'paymentMethod not exist';
            }
            if ($paymentMethod->status == 'Disable') {
                return 'paymentMethod is Disable';
            }
           
            if ($gatewayPaymentChannel->risk_control == 1) {
                // daily transection limit checking
                $checkLimitationRiskMode = $this->checkLimitationRiskMode($gatewayPaymentChannel, $paymentMap);
                if ($checkLimitationRiskMode) {
                    $getGatewayParameters = $this->getGatewayParameters($gatewayPaymentChannel);
                } else {
                    return $checkLimitationRiskMode;
                }
                // daily transection limit checking
            } else {
                $getGatewayParameters = $this->getGatewayParameters($gatewayPaymentChannel);
            }
        } else {
            $gatewayPaymentChannel = GatewayPaymentChannel::whereIn('id', explode(',', $paymentMap->gateway_payment_channel_id))->get();
            if (! $gatewayPaymentChannel) {
                return 'gatewayPaymentChannel not exist';
            }

            foreach ($gatewayPaymentChannel as $item) {
                if ($item->status == 'Enable') {
                    $paymentMethod = PaymentMethod::where('id', $item->gateway_account_method_id)->first();
                    $arrayData['method_name'] = $paymentMethod->method_name;
                    if (! $paymentMethod) {
                        return 'paymentMethod not exist';
                    }
                    if ($paymentMethod->status == 'Disable') {
                        return 'paymentMethod is Disable';
                    }
                    // gateway_account_method_id
                    if ($item->risk_control == 1) {
                        // daily transection limit checking
                        $checkLimitationRiskMode = $this->checkLimitationRiskMode($item, $paymentMap);
                        if ($checkLimitationRiskMode) {
                            $getGatewayParameters = $this->getGatewayParameters($item);
                            $gatewayPaymentChannel = $item;
                        } else {
                            return $checkLimitationRiskMode;
                        }
                        // daily transection limit checking
                    } else {
                        $getGatewayParameters = $this->getGatewayParameters($item);
                    }
                }
            }
        }
        $res = array_merge($arrayData, $getGatewayParameters);
        $frtransaction = $this->generateUniqueCode();
        // echo "<pre>";  print_r($res); die;
        // Call Curl API code START
        $response = Http::withHeaders([
            'apikey' => $res['apiKey'],
            'Content-Type' => 'application/json',
        ])->post($res['api_url'], [
            'client_email_id' => $request->customer_email,
            'amount' => $request->amount,
            'client_preferred_fiat_currency' => $request->Currency,
            'merchant_id' => $res['merchant_id'],
            'merchant_website' => url('ipint/deposit/gatewayResponse'), 
            'invoice_callback_url' => url('api/ipintDeposit/WebhookNotifiication'),
        ]);

        $result = $response->json();
        //  echo "<pre>";  print_r($result); die;
        $client_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
        if (!empty($result['payment_process_url'])) {
                    //Insert data into DB
                    $addRecord = [
                        'agent_id' => $merchantData->agent_id,
                        'merchant_id' => $merchantData->id,
                        'merchant_code' => $request->merchant_code,
                        'TransId' => $result['session_id'] ?? '',
                        'transaction_id' => $request->referenceId,
                        'fourth_party_transection' => $frtransaction,
                        'callback_url' => $request->callback_url,
                        'amount' => $request->amount,
                        'Currency' => $request->Currency,
                        'product_id' => $request->product_id,
                        'payment_channel' => $gatewayPaymentChannel->id,
                        'payment_method' => $paymentMethod->method_name,
                        'request_data' => json_encode($res),
                        'gateway_name' => 'Ipint Crypto',
                        'customer_name' => $request->customer_name,
                        'customer_email' => $request->customer_email,
                        'payin_arr' => json_encode($result),
                        'receipt_url' => $result['payment_process_url'],
                        'ip_address' => $client_ip,      
                    ];
                //  echo "<pre>";  print_r($addRecord); die;
                PaymentDetail::create($addRecord);
                return redirect($result['payment_process_url']);
        } else {
                    $addRecord = [
                        'agent_id' => $merchantData->agent_id,
                        'merchant_id' => $merchantData->id,
                        'merchant_code' => $request->merchant_code,
                        'TransId' => $result['session_id'] ?? '',
                        'transaction_id' => $request->referenceId,
                        'fourth_party_transection' => $frtransaction,
                        'callback_url' => $request->callback_url,
                        'amount' => $request->amount,
                        'Currency' => $request->Currency,
                        'product_id' => $request->product_id,
                        'payment_channel' => $gatewayPaymentChannel->id,
                        'payment_method' => $paymentMethod->method_name,
                        'request_data' => json_encode($res),
                        'gateway_name' => 'Ipint Crypto',
                        'customer_name' => $request->customer_name,
                        'customer_email' => $request->customer_email,
                        'payin_arr' => json_encode($result),
                        'payment_status' => 'failed',
                        'receipt_url' => $result['message'],
                        'ip_address' => $client_ip,      
                    ];
                PaymentDetail::create($addRecord);
                echo "Unexpected Response"; echo "<pre>"; print_r($result); die;
        }
      
    }

    public function ipintDepositGatewayResponse(Request $request)
    {
        $response = $request->all();
        // echo "<pre>"; print_r($response); 
        $invoiceId = $response['invoice_id'];
        //Generate Signature START
        $nonce = time() * 1000;
        $apiPath = "/invoice?id={$invoiceId}";
        $apiSecret = '2TLcHzh13meEXwX1eruGVCiKoNVF4bRT72QhXc5d1hyq5EdcwPzsbNCgPquyZ6JZo';
        $sig = "/api/{$nonce}{$apiPath}";
        $signature = hash_hmac('sha384', $sig, $apiSecret, false);
         //Generate Signature END
        $response = Http::withHeaders([
            'content-type' => 'application/json',
            'apikey' => '2F4yX41QTva26mi5p5SsaqeLo4idFrye4HpqDcFNtuL4irD29uxiA39M1gsC3wFwU',
            'signature' => $signature,
            'nonce' => $nonce,
        ])->get("https://api.ipint.io:8003/invoice", [
            'id' => $invoiceId
        ]);
        $result = $response->json();
        //   echo "<pre>"; print_r($result); die;
        if ($response->successful() && isset($result['data'])) {
            $Transactionid = $result['data']['invoice_id'];
            $orderstatus = match ($result['data']['transaction_status'] ?? null) {
                'COMPLETED' => 'success',
                'CHECKING' => 'pending',
                'PROCESSING' =>  'processing',
                default => 'failed',
            };
            $receivedCryptoAmount = !empty($result['data']['received_crypto_amount']) ? $result['data']['received_crypto_amount'] : $result['data']['invoice_crypto_amount'];
            $cryptoCurrency = $result['data']['transaction_crypto'];
                $updateData = [
                    'amount' => $receivedCryptoAmount,
                    'Currency' => $cryptoCurrency,
                    'payment_status' => $orderstatus,
                    'payin_arr' => json_encode($result)
                ];
                PaymentDetail::where('TransId', $Transactionid)->update($updateData);
                $paymentDetail = PaymentDetail::where('TransId', $Transactionid)->first();
                $callbackUrl = $paymentDetail->callback_url;
                $postData = [
                    'merchant_code' => $paymentDetail->merchant_code,
                    'referenceId' => $paymentDetail->transaction_id,
                    'transaction_id' => $paymentDetail->fourth_party_transection,
                    'amount' => $paymentDetail->amount,
                    'Currency' => $paymentDetail->Currency,
                    'customer_name' => $paymentDetail->customer_name,
                    'payment_status' => $paymentDetail->payment_status,
                    'created_at' => $paymentDetail->created_at,
                ];
                return view('payment.payment_status', compact('request', 'postData', 'callbackUrl'));
            
        }else{
            echo "Unexpected Response"; echo "<pre>"; print_r($result); die;
        }

    }

    public function ipintdepositResponse(Request $request)
    {
        $data = $request->all();
        echo "Transaction Information as follows" . '<br/>' .
            "Merchant_code : " . $data['merchant_code'] . '<br/>' .
            "ReferenceId : " . $data['referenceId'] . '<br/>' .
            "TransactionId : " . $data['transaction_id'] . '<br/>' .
            "Type : Crypto Deposit" .'<br/>' .
            "Currency : " . $data['Currency'] . '<br/>' .
            "Amount : " . $data['amount'] . '<br/>' .
            "customer_name : " . $data['customer_name'] . '<br/>' .
            "Datetime : " . $data['created_at'] . '<br/>' .
            "Status : " . $data['payment_status'];
         die;
    }

    public function ipintDepositWebhookNotifiication(Request $request)
    {
       // { "invoice_id": 'invoice id', "status": "COMPLETED" }     //FAILED/COMPLETED 
        // Decode the JSON payload automatically
        $results = $request->json()->all();
        if(!empty($results)) {
            // Extract data
            $transactionId = $results['invoice_id'] ?? null;
            $orderStatus = match ($results['status'] ?? '') {
                'COMPLETED' => 'success',
                'FAILED' => 'failed',
                default => 'failed',
            };
            $updateData = [
                'payment_status' => $orderStatus,
                'response_data' => json_encode($results),
            ];
            PaymentDetail::where('TransId', $transactionId)->update($updateData);
            echo "Transaction updated successfully!";
            //Call webhook API START
            $paymentDetail = PaymentDetail::where('TransId', $transactionId)->first();
            $callbackUrl = $paymentDetail->callback_url;
            $postData = [
                'merchant_code' => $paymentDetail->merchant_code,
                'referenceId' => $paymentDetail->transaction_id,
                'transaction_id' => $paymentDetail->fourth_party_transection,
                'amount' => $paymentDetail->amount,
                'Currency' => $paymentDetail->Currency,
                'customer_name' => $paymentDetail->customer_name,
                'payment_status' => $paymentDetail->payment_status,
                'created_at' => $paymentDetail->created_at,
            ];
            // echo "<pre>";  print_r($postData); die;
            try {
                if ($paymentDetail->callback_url != null) {
                    $response = Http::post($paymentDetail->callback_url, $postData);
                    echo $response->body(); die;
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to call webhook','message' => $e->getMessage()], 500);
            }
             //Call webhook API START

        }else{
            return response()->json(['error' => 'Data Not Found or Invalid Request!'], 400);
        }



    }

    public function getGatewayParameters($gatewayPaymentChannel): array
    {
        $arrayData = [];
        //   dd($gatewayPaymentChannel->gateway_account_method_id);
        $gatewayAccountMethod = GatewayAccountMethod::where('method_id', $gatewayPaymentChannel->gateway_account_method_id)->where('gateway_account_id', $gatewayPaymentChannel->gateway_account_id)->first();
        //dd($gatewayAccountMethod);
        if (! $gatewayAccountMethod) {
            return 'gatewayAccountMethod not exist';
        }
        if ($gatewayAccountMethod->status == 'Disable') {
            return 'gatewayAccountMethod is Disable';
        }
        // return $gatewayAccountMethod;
        $gatewayAccount = GatewayAccount::where('id', $gatewayPaymentChannel->gateway_account_id)->first(); // web site details
        $arrayData['e_comm_website'] = $gatewayAccount->e_comm_website;
        if (! $gatewayAccount) {
            return 'GatewayAccount not exist';
        }
        if ($gatewayAccount->status == 'Disable') {
            return 'GatewayAccount is Disable';
        }

        $parameterSetting = ParameterSetting::where('channel_id', $gatewayAccount->gateway)->get();

        $parameterValue = ParameterValue::where('gateway_account_method_id', $gatewayAccountMethod->id)->get();
        //dd($parameterValue);
        foreach ($parameterSetting as $parameterSettingVal) {
            foreach ($parameterValue as $parameterValueVal) {
                if ($parameterValueVal->parameter_setting_id == $parameterSettingVal->id) {
                    // $arrayData[str_replace(' ', '_', strtolower($parameterSettingVal->parameter_name))] = $parameterValueVal->parameter_setting_value;
                    $arrayData[$parameterSettingVal->parameter_name] = $parameterValueVal->parameter_setting_value;
                }
            }
        }

        return $arrayData;
    }

    public function generateUniqueCode()
    {
        $mytime = Carbon::now();
        $currentDateTime = str_replace(' ', '', $mytime->parse($mytime->toDateTimeString())->format('Ymd His'));
        $fourth_party_transection = $currentDateTime.random_int(1000, 9999);
        return 'TR'.$fourth_party_transection;
    }

}
