<?php
require_once '../common.php';
require_once '../config.php';
require_once 'stripe.php';
require_once 'paypal.php';
use App\Common;
use App\Config;
use App\Models\DB;

$body = file_get_contents('php://input');
parse_str($body,  $data);

$appName = isset($data['app_name']) ? Common::cleanQuery($data['app_name']) : 'vidcombo';
$payGate = isset($data['pay_gate']) ? Common::cleanQuery($data['pay_gate']) : '';
$license_key = isset($data['license_key']) ? Common::cleanQuery($data['license_key']) : '';
$plan_alias = isset($data['plan']) ? Common::cleanQuery($data['plan']) : '';
$bank_name = Common::getString('bank_name', 'Stripe');
$func = Common::getString('func');

//0. Nếu không có licenseKey, tạo URL chuyển trang
if ($func == 'create-checkout-session' && empty($license_key)) {
    $encodedPlan = base64_encode($plan_alias);
    $encodedappName = base64_encode($appName);
    if($appName == 'vidobo'){
        $url = "https://www.vidobo.net/pay?planName=" . urlencode($encodedPlan) . "&appName=" . urlencode($encodedappName);
    }else{
        $url = "https://www.vidcombo.com/pay?planName=" . urlencode($encodedPlan) . "&appName=" . urlencode($encodedappName);
    }

    // Trả về URL chuyển trang
    $response = [
        'session' => [
            'url' => $url
        ]
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$client_id = ''; $clientSecret = ''; $connection = '';
$row_licensekey = array();


//1. gọi thanh toán mới từ landing page
if($plan_alias && $appName && $payGate && !$license_key){
    if(strtolower($payGate) == 'stripe')
    {
        $stripeWebhook = new StripeWebhook();
        $stripeWebhook->initByAppName($appName);
        return $stripeWebhook->createPaySessionStripe($plan_alias);
    }
    elseif (strtolower($payGate) == 'paypal'){

        $paypalWebhook = new PaypalWebhook();
        $paypalWebhook->initByAppName($appName);
        return $paypalWebhook->createPaySessionPaypal($plan_alias);
    }
    exit;
}

//2. Gọi từ webhook.
if ($bank_name && $func=='webhook') {
    if (!isset(Config::$banks[$bank_name])) {
        die("Invalid bank name: {$bank_name}");
    }
    if(strpos(strtolower($bank_name),'stripe')!==false)
    {
        error_log('stripe webhook' . $bank_name);
        $stripeWebhook = new StripeWebhook();
        $stripeWebhook->initByBankName($bank_name);
        $stripeWebhook->handleWebhook();
    }
    elseif (strpos(strtolower($bank_name), 'paypal')!==false){
        error_log('paypal webhook' . $bank_name);
        $paypalWebhook = new PaypalWebhook();
        $paypalWebhook->initByBankName($bank_name);
        $paypalWebhook->handlePaypalWebhook();
    }
    exit;
}

//3. nâng cấp gói. Gọi từ app
if ($license_key) {
    $db = new DB();
    $db->setTable('licensekey');

    // Step 1: Get subscription_id, sk_key, and sign_key from licensekey table
    $itemSelectKey = [
        'subscription_id',
        'sk_key',
        'sign_key'
    ];
    $row_licensekey = $db->selectRow($itemSelectKey, ['license_key' => $license_key]);
    $subscriptionsId = $row_licensekey['subscription_id'] ?? null;

    // Initialize sk_key and sign_key
    $client_id = $row_licensekey['sk_key'] ?? '';
    $clientSecret = $row_licensekey['sign_key'] ?? '';
    error_log($client_id . ': ' . $clientSecret);

    // Step 2: Retrieve bank_name from subscriptions table using subscription_id
    if ($subscriptionsId) {
        $db->setTable('subscriptions');
        $itemSelectSub = ['bank_name', 'app_name'];
        $row_subscription = $db->selectRow($itemSelectSub, ['subscription_id' => $subscriptionsId]);

        $bank_name = $row_subscription['bank_name'] ?? '';
        $app_name = $row_subscription['app_name'] ?? '';
    } else {
        $bank_name = ''; 
    }
    $convertname = strtolower($bank_name);
    if ($bank_name == 'Stripe') {
        $stripeWebhook = new StripeWebhook();
        $stripeWebhook->initByAppName($appName);
        $stripeWebhook->updateSubStripe($license_key, $plan_alias, $convertname);
    } else {
        $paypalWebhook = new PaypalWebhook();
        $paypalWebhook->initByAppName($appName);
        $paypalWebhook->reviseSubscription($license_key, $convertname, $plan_alias);
    }
}

if(!$client_id || !$clientSecret){
    die('Invalid config');
}

