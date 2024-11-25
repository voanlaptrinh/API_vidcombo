<?php
require_once '../common.php';
require_once '../vendor/autoload.php';
require_once '../config.php';
require_once './../stripe/paypal.php';
$body = file_get_contents('php://input');
parse_str($body,  $data);

$config = new Config();

$appName = isset($data['app_name']) ? $data['app_name'] : 'vidcombo';
$bankName = isset($data['bank_name']) ? $data['bank_name'] : '';

$name = isset($_GET['name']) ? trim(string: $_GET['name']) : ''; // Default to 'stripe'




if (!empty($name)) {
    if (!isset($config->banks[$name])) {
        throw new Exception("Invalid bank name: {$name}");
    }

    $bankConfig = $config->banks[$name];
    error_log("Using bank configuration for name: {$name}");

    $client_id = $bankConfig['client_id'] ?? null;
    $clientSecret = $bankConfig['client_secret'] ?? null;
} else {
    // Nếu `name` không tồn tại, dùng `appName` và `bankName`
    if (!empty($appName)) {
        if (!isset($config->apps[$appName])) {
            throw new Exception('Invalid app name.');
        }

        $bankKey = $config->apps[$appName][$bankName] ?? null;

        if (!isset($config->banks[$bankKey])) {
            throw new Exception("Invalid bank key for app: {$appName} and bank: {$bankName}");
        }

        $bankConfig = $config->banks[$bankKey];
        error_log("Using bank configuration for appName: {$appName}, bankName: {$bankName}");
        error_log(print_r($bankConfig, true));

        $client_id = $bankConfig['client_id'] ?? null;
        $clientSecret = $bankConfig['client_secret'] ?? null;
    } else {
        throw new Exception('Both appName and name are empty.');
    }
}


error_log('client_id' . $client_id . '\n' . $clientSecret);



// Kiểm tra URL và gọi hàm xử lý tương ứng
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$apiContext = new \PayPal\Rest\ApiContext(
    new \PayPal\Auth\OAuthTokenCredential(
        $bankConfig['client_id'],     // Replace with your PayPal Client ID
        $bankConfig['client_secret']  // Replace with your PayPal Client Secret
    )
);
$apiContext->setConfig(['mode' => 'sanbox']);


$func = isset($_GET['func']) ? trim($_GET['func']) : '';


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
$access_token = get_paypal_access_token($client_id, $clientSecret);

$paypal_funtion = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
switch ($func) {

    // case 'create-checkout-session':
    //     $paypal_funtion->createPaySessionPaypal($access_token);
    //     break;
    case 'revise-subscription':
        $paypal_funtion->reviseSubscription($access_token);
        break;
    case 'update-subscription':
        $paypal_funtion->upSubscription($access_token);
        break;
    case 'status-subscription':
        $paypal_funtion->getSubscriptionStatus($access_token);
        break;
    case 'list-subscription':
        $paypal_funtion->listSubscriptions($access_token);
        break;
    case 'create-product':
        $paypal_funtion->createProduct($access_token);
        break;
    case 'list-products':
        $paypal_funtion->listProducts($access_token);
        break;
    case 'cancel-products':
        $paypal_funtion->cancelSubscription($access_token);
        break;
    case 'delete-products':
        $paypal_funtion->deleteProducts($access_token);
        break;
    case 'create-plans':
        $paypal_funtion->createPlans($access_token);
        break;
    case 'list-plans':
        $paypal_funtion->listPlans($access_token);
        break;
    case 'detail-plans':
        $paypal_funtion->getPlanDetails($access_token);
        break;
    case 'delete-plans':
        $paypal_funtion->deleteAllPlans($access_token);
        break;
    // case 'webhook':
    //     $paypal_funtion->handlePaypalWebhook();
    //     break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
}


