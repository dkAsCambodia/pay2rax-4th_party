
@extends('layouts.app')
@section('content')
<?php
    $referenceNo="GZTRN".time().generateRandomString(3);
    function generateRandomString($length = 3) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {$randomString .= $characters[rand(0, $charactersLength - 1)];}
        return $randomString;
    }
?>
<style>
    .form-control {
    height: 2.5rem !important;
}
 /* Fullscreen spinner container */
 .spinner-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Opaque background */
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        /* Spinner image */
        .spinner-container img {
            width: 100px;
            height: 100px;
        }
</style>
{!! Toastr::message() !!}
<div class="row justify-content-center h-100 align-items-center">
    <div class="col-md-8">
        <div class="authincation-content">
            <div class="row no-gutters">
                <div class="col-xl-12">
                    <div class="auth-form">
                        <h3 class="text-center mb-4"><b>Pay2rax Transfer or Deposit</b></h3>
                        <form class="form-horizontal" action="{{ route('apiroute.banksy.checkout') }}" method="GET" id="paymentForm">
                            <input type="hidden" name="merchant_code" value="testmerchant005">
                            <input type="hidden" name="product_id" value="19">
                            <input type="hidden" name="callback_url" value="{{ route('apiroute.depositResponse') }}">
							<div class="row mb-4">
                                <label for="Reference" class="col-md-3 form-label">Reference ID</label>
                                <div class="col-md-9">
								<input class="form-control" name="referenceId" placeholder="Enter Reference ID" value="<?php echo $referenceNo; ?>" required readonly type="text">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <label for="Currency" class="col-md-3 form-label">Currency</label>
                                <div class="col-md-9">
										<select class="form-control select2-show-search form-select  text-dark" id="currency" name="currency" required data-placeholder="---" tabindex="-1" aria-hidden="true">
											<option value="">---</option>
											<option value="EUR" selected>EUR</option>
											<option value="MYR">MYR</option>
                                            <option value="THB">THB</option>
                                            <option value="VND">VND</option>
                                            <option value="IDR">IDR</option>
                                            <option value="USD">USD</option>
                                            <option value="PHP">PHP</option>
                                            <option value="INR">INR</option>
                                            <option value="CNY">CNY</option>
										</select>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <label for="price" class="col-md-3 form-label">Amount</label>
                                <div class="col-md-9">
									<input class="form-control" required name="amount" placeholder="Enter your Amount" value="1000" type="text">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <label for="customer_name" class="col-md-3 form-label">Customer Name</label>
                                <div class="col-md-9">
								<input class="form-control" required name="customer_name" id="customer_name" placeholder="Enter Customer Name" type="text" value="">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <label for="customer_email" class="col-md-3 form-label">Customer Email</label>
                                <div class="col-md-9">
								<input class="form-control" required name="customer_email" id="customer_email" placeholder="Enter Customer email" type="email" value="" >
                                </div>
                            </div>
                             <!-- Spinner -->
                            <div class="spinner-container">
                                <img src="https://i.gifer.com/ZZ5H.gif" alt="Loading..."> <!-- Replace with your spinner image URL -->
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-block">Pay Now</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const form = document.getElementById('paymentForm');
    const spinnerContainer = document.querySelector('.spinner-container');
    form.addEventListener('submit', function () {
        spinnerContainer.style.display = 'flex'; // Show spinner with opaque background
    });
</script>
@endsection
