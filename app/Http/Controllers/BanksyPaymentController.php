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
use App\Models\PaymentMap;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Session;

class BanksyPaymentController extends Controller
{
    public function banksyCheckout(Request $request)
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
        // Call Curl API code START
        // Session::put('bnksessTransId', $frtransaction);
        $headers = [
            'Content-Type' => 'application/json', 
            'X-AUTH' => $res['apiKey'],
        ];
        $postData = [
            'amount' => $request->amount,
            'currency' => $request->currency,
            'successCallback' => url('bnkdeposit_success/'.$frtransaction),  
            'failureCallback' => url('bnkdeposit_fail/'.$frtransaction),
            'currencyType' => $res['currencyType'],
            'isKycOptional' => true,
            'customerEmail' => $request->customer_email,
        ];
        $response = Http::withHeaders($headers)->post($res['api_url'], $postData);
        $jsonData = $response->json();
        // Redirect to the payment link
        if (isset($jsonData['paymentLink'])) {
            //Insert data into DB
            $res['customer_email'] = $request->customer_email;  
            $client_ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
            $addRecord = [
                'agent_id' => $merchantData->agent_id,
                'merchant_id' => $merchantData->id,
                'merchant_code' => $request->merchant_code,
                'transaction_id' => $request->referenceId,
                'fourth_party_transection' => $frtransaction,
                'callback_url' => $request->callback_url,
                'amount' => $request->amount,
                'Currency' => $request->currency,
                'product_id' => $request->product_id,
                'payment_channel' => $gatewayPaymentChannel->id,
                'payment_method' => $paymentMethod->method_name,
                'request_data' => json_encode($res),
                'gateway_name' => 'BankSy Gateway',
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                // 'payin_arr' => '',
                'receipt_url' => $jsonData['paymentLink'],
                'ip_address' => $client_ip,
            ];
              // echo "<pre>";  print_r($addRecord); die;
            PaymentDetail::create($addRecord);
            return redirect($jsonData['paymentLink']);
        }else{
            echo "Unexpected Response : Payment link not found."; echo "<pre>"; print_r($jsonData); die;
        }

    }

    public function bnkdeposit_success(Request $request, $bnksessTransId)
    {
        $updateData = [
            'TransId' => $request->paymentId,
            'payment_status' => 'success',
            'payin_arr' => '',
        ];
        PaymentDetail::where('fourth_party_transection', $bnksessTransId)->update($updateData);
        $paymentDetail = PaymentDetail::where('fourth_party_transection', $bnksessTransId)->first();
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
    }

    public function bnkdeposit_fail(Request $request, $bnksessTransId)
    {
        $updateData = [
            'TransId' => $request->paymentId,
            'payment_status' => 'failed',
            'payin_arr' => '',
        ];
        PaymentDetail::where('fourth_party_transection', $bnksessTransId)->update($updateData);
        $paymentDetail = PaymentDetail::where('fourth_party_transection', $bnksessTransId)->first();
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
    }

    public function bnkCardDepositform(Request $request)
    {
        return view('payment-form.bnks.bnk-form');
    }

    public function depositResponse(Request $request)
    {
        $data = $request->all();
        echo "Transaction Information as follows" . '<br/>' .
            "Merchant_code : " . $data['merchant_code'] . '<br/>' .
            "ReferenceId : " . $data['referenceId'] . '<br/>' .
            "TransactionId : " . $data['transaction_id'] . '<br/>' .
            "Type : Deposit" .'<br/>' .
            "Currency : " . $data['Currency'] . '<br/>' .
            "Amount : " . $data['amount'] . '<br/>' .
            "customer_name : " . $data['customer_name'] . '<br/>' .
            "Datetime : " . $data['created_at'] . '<br/>' .
            "Status : " . $data['payment_status'];
         die;
    }

    public function bnkWebhookNotifiication(Request $request)
    {
        // {
        //     "paymentId": "6798540ecd9360596386289c",
        //     "paymentRaw": {
        //       "id": "6798540ecd9360596386289c",
        //       "keyUsed": "ck_live_595cd157-e6f2-40e1-8f9e-3361a8618598",
        //       "amount": 500,
        //       "currency": "THB",
        //       "successCallback": "https://payment.pay2rax.com/bnkdeposit_success/TR202501281050378124",
        //       "failureCallback": "https://payment.pay2rax.com/bnkdeposit_fail/TR202501281050378124",
        //       "status": "failed",     //success/awaiting/pending
        //       "environment": "live",
        //       "createdAt": "2025-01-28T03:50:39.315Z"
        //     }
        //   }

        // Decode the JSON payload automatically
        $results = $request->json()->all();
        if(!empty($results)) {
            // Extract data
            $transactionId = $results['paymentId'] ?? null;
            $status = $results['paymentRaw']['status'] ?? 'unknown';
            if ($status === 'success') {
                $orderStatus = 'success';
            } elseif (in_array($status, ['awaiting', 'pending'])) {
                $orderStatus = 'processing';
            } else {
                $orderStatus = 'failed';
            }
            // Simulate delay
            sleep(20);
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
