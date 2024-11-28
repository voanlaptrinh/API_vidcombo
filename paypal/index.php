<?php
require_once '../vendor/autoload.php';
// require_once '../config.php';
require_once './../stripe/paypal.php';
$body = file_get_contents('php://input');
parse_str($body,  $data);

use App\Common;
use App\Config;
use App\Models\DB;

$config = new Config();

$appName = isset($data['app_name']) ? $data['app_name'] : 'vidcombo';
$bankName = isset($data['bank_name']) ? $data['bank_name'] : 'paypal';

$appsNamepays = Config::$apps[$appName]['paypal'];
if (!empty($appName)) {

    $bankConfig = Config::$banks[$appsNamepays];


    $client_id = $bankConfig['api_key'] ?? null;
    $clientSecret = $bankConfig['secret_key'] ?? null;
} else {
    throw new Exception('Both appName and name are empty.');
}



error_log('client_id' . $client_id . '\n' . $clientSecret);



// Kiểm tra URL và gọi hàm xử lý tương ứng
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$apiContext = new \PayPal\Rest\ApiContext(
    new \PayPal\Auth\OAuthTokenCredential(
        $bankConfig['api_key'],     // Replace with your PayPal Client ID
        $bankConfig['secret_key']  // Replace with your PayPal Client Secret
    )
);
$apiContext->setConfig(['mode' => 'sanbox']);


$func = isset($_GET['func']) ? trim($_GET['func']) : '';


$paypal_funtion = new PaypalWebhook();
$paypal_funtion->initByAppName($appName);
switch ($func) {

        // case 'create-checkout-session':
        //     $paypal_funtion->createPaySessionPaypal($access_token);
        //     break;
        // case 'revise-subscription':
        //     $paypal_funtion->reviseSubscription();
        //     break;
        // case 'update-subscription':
        //     $paypal_funtion->upSubscription();
        //     break;

    case 'create-product':  //THêm mới product
        $paypal_funtion->createProduct();
        break;
    case 'list-products': //Hiện toàn bộ gói sub
        $paypal_funtion->listProducts();
        break;

        
    case 'status-subscription':  //Trạng thái gói sub
        $paypal_funtion->getSubscriptionStatus();
        break;
    case 'cancel-subscription': //cancel product
        $paypal_funtion->cancelSubscription();
        break;

    case 'create-plans':  //Tạo mới plan
        $paypal_funtion->createPlans();
        break;
    case 'list-plans':  //Show all plans
        $paypal_funtion->listPlans();
        break;
    case 'detail-plans': //Chi tiết plan
        $paypal_funtion->getPlanDetails();
        break;

        // case 'webhook':
        //     $paypal_funtion->handlePaypalWebhook();
        //     break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
}
