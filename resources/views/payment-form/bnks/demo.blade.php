<?php
$referenceNo="GZTRN".time().generateRandomString(3);
    function generateRandomString($length = 3) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {$randomString .= $characters[rand(0, $charactersLength - 1)];}
        return $randomString;
    }
// $url = "http://127.0.0.1:8000/api/bnk/checkout";
$apiUrl = "https://payment.pay2rax.com/api/bnk/checkout";

$data = [
    'merchant_code' => $_GET['merchant_code'],
    'product_id' => '19',
    'referenceId' => $referenceNo, 
    // 'callback_url' => 'http://127.0.0.1:8000/depositResponse',
    'callback_url' => 'https://payment.pay2rax.com/api/depositResponse',
    'currency' =>  $_GET['currency'], 
    'amount' => $_GET['amount'],   
    'customer_email' => 'dk@gmail.com', 
    'customer_name' => 'dk John Doe', 
];
$fullUrl = $apiUrl . '?' . http_build_query($data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background: #000;
        }
        .loading-container { text-align: center; }
        .loading-container img { width: 100px; height: 100px;}
        p{ color:white; }
    </style>
</head>
<body>
    <div class="loading-container">
        <img src="https://i.gifer.com/ZZ5H.gif" alt="Loading...">
        <p>Processing, please wait...</p>
    </div>
    <script>
        setTimeout(() => {
            window.location.href = '<?php echo $fullUrl; ?>';
        }, 2000); // 2-second delay for demo purposes (optional)
    </script>
</body>
</html>
