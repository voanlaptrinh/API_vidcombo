<?php
require_once '../common.php';
require_once '../config.php';
require_once 'stripe.php';
require_once 'paypal.php';


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
    $url = "https://vidcombo.com/pay?planName=" . urlencode($encodedPlan) . "&appName=" . urlencode($encodedappName);

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
        $stripeWebhook = new StripeWebhook();
        $stripeWebhook->initByBankName($bank_name);
        $stripeWebhook->handleWebhook();
    }
    elseif (strpos(strtolower($bank_name),'paypal')!==false){
        $stripeWebhook = new StripeWebhook();
        $stripeWebhook->initByBankName($bank_name);
        $stripeWebhook->handleWebhook();
    }
    exit;
}

//3. nâng cấp gói. Gọi từ app
if ($license_key)
{
    $connection = Common::getDatabaseConnection();
    $stmt = $connection->prepare("SELECT `subscription_id`, `sk_key`, `sign_key` FROM `licensekey` WHERE `license_key` = :license_key");
    $stmt->execute([':license_key' => $license_key]);
    $row_licensekey = $stmt->fetch(PDO::FETCH_ASSOC);

    $client_id = isset($row_licensekey['sk_key'])?$row_licensekey['sk_key']:'';
    $clientSecret = isset($row_licensekey['sign_key'])?$row_licensekey['sign_key']:'';
}

if(!$client_id || !$clientSecret){
    die('Invalid config');
}


// $stripe_funtion = new StripeApiFunction($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
switch ($func) {
    case 'create-checkout-session':
        if ($license_key) {
            $bank_name = getBackNameByLicenseKey($license_key, $connection);
            $convertname = strtolower($bank_name);
            if (strpos($convertname, 'stripe')!==false) {
                $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
                $stripeWebhook->updateSubStripe($license_key, $plan, $convertname);
            } else {
                $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
                $paypalWebhook->updateSubPaypal($license_key, $plan, $convertname);
            }
        }
        break;
    case 'create-pay-stripe':
        $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $stripeWebhook->createPaySessionStripe();
        //pp 
        break;
    case 'create-pay-paypal':
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->createPaySessionPaypal();
        //pp 
        break;

    case 'webhookStripe':
        $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $stripeWebhook->handleWebhook();
        break;



        //phần xử lý của paypal tạo gói

    case 'revise-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->reviseSubscription($access_token);
        break;
    case 'update-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->upSubscription($access_token);
        break;
    case 'status-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->getSubscriptionStatus($access_token);
        break;
    case 'list-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->listSubscriptions($access_token);
        break;
    case 'create-product':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->createProduct($access_token);
        break;
    case 'list-products':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->listProducts($access_token);
        break;
    case 'cancel-products':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->cancelSubscription($access_token);
        break;
    case 'delete-products':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->deleteProducts($access_token);
        break;
    case 'create-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->createPlans($access_token);
        break;
    case 'list-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->listPlans($access_token);
        break;
    case 'detail-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->getPlanDetails($access_token);
        break;
    case 'delete-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->deleteAllPlans($access_token);
        break;
        //end 
        //webhookpaypal
    case 'webhookPaypal':
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $paypalWebhook->handlePaypalWebhook();
        break;
        //end

    case 'check-subscription':
        $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $payGate, $config->apps, $license_key);
        $stripeWebhook->checkSubscription();
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
}

function get_paypal_access_token($client_id, $clientSecret)
{
    $url = "https://api.paypal.com/v1/oauth2/token";
    $headers = [
        "Authorization: Basic " . base64_encode("$client_id:$clientSecret"),
        "Content-Type: application/x-www-form-urlencoded"
    ];
    $data = "grant_type=client_credentials";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response);
    return $result->access_token;
}

function getBackNameByLicenseKey($licenseKey, $connection)
{
    try {
        // First query to get the subscription_id from the licensekey table
        $stmt =  $connection->prepare("SELECT subscription_id FROM licensekey WHERE license_key = :licenseKey");
        $stmt->execute([':licenseKey' => $licenseKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if a subscription_id was found
        if ($result && isset($result['subscription_id'])) {
            $subscriptionId = $result['subscription_id'];

            // Second query to get the back_name from the subscriptions table
            $stmt =  $connection->prepare("SELECT bank_name FROM subscriptions WHERE subscription_id = :subscriptionId");
            $stmt->execute([':subscriptionId' => $subscriptionId]);
            $subscriptionResult = $stmt->fetch(PDO::FETCH_ASSOC);

            // Return the bank_name if found
            if ($subscriptionResult && isset($subscriptionResult['bank_name'])) {
                return $subscriptionResult['bank_name'];
            } else {
                return "bank_name not found for the given subscription_id.";
            }
        } else {
            return "subscription_id not found for the given license_key.";
        }
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}
