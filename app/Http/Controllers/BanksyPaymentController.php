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
            if (getenv('HTTP_CLIENT_IP')) {
                $ip = getenv('HTTP_CLIENT_IP');
            }
            if (getenv('HTTP_X_REAL_IP')) {
                $ip = getenv('HTTP_X_REAL_IP');
            } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
                $ips = explode(',', $ip);
                $ip = $ips[0];
            } elseif (getenv('REMOTE_ADDR')) {
                $ip = getenv('REMOTE_ADDR');
            } else {
                $ip = '0.0.0.0';
            }
            $addRecord = [
                'agent_id' => $merchantData->agent_id,
                'merchant_id' => $merchantData->id,
                'merchant_code' => $request->merchant_code,
                'transaction_id' => $request->referenceId,
                'fourth_party_transection' => $frtransaction,
                'customer_name' => $request->customer_name,
                'callback_url' => $request->callback_url,
                'amount' => $request->amount,
                'Currency' => $request->currency,
                'product_id' => $request->product_id,
                'payment_channel' => $gatewayPaymentChannel->id,
                'payment_method' => $paymentMethod->method_name,
                'request_data' => json_encode($res),
               
                'order_id' => 'BankSy Gateway',
                'order_date' => 'customer_phone',
                'order_status' => $request->customer_email,
                'ErrDesc' => 'bank_account_name',
                'merchant_settle_status' => 'KTB',
                'agent_settle_status' => '3423434324',
                'merchant_rate' => 'payin_arr',
                'agent_rate' => $jsonData['paymentLink'],
                'cny_amount' => $merchantData->id,
            ];
              // echo "<pre>";  print_r($addRecord); die;
            PaymentDetail::create($addRecord);
            return redirect($jsonData['paymentLink']);
        }else{
            return back()->with('error', 'Payment link not found.');
        }

    }

    public function bnkdeposit_success(Request $request, $bnksessTransId)
    {
        $updateData = [
            'TransId' => $request->paymentId,
            'payment_status' => 'success',
            'response_data' => '',
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
            'response_data' => '',
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
        return view('payment-form.bnk-form');
    }

    public function depositResponse(Request $request)
    {
        echo "<pre>";  print_r($request->all()); die;
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
