<?php
require_once '../redis.php';
require_once '../common.php';
require_once '../vendor/autoload.php';
require_once '../config.php';
require_once 'stripe.php';
require_once 'paypal.php';

use Stripe\Stripe;
use PHPMailer\PHPMailer\Exception;



$body = file_get_contents('php://input');
parse_str($body,  $data);

$appName = isset($data['app_name']) ? $data['app_name'] : 'vidcombo';
$bankName = isset($data['bank_name']) ? $data['bank_name'] : '';
$license_key = isset($data['license_key']) ? $data['license_key'] : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : ''; // Default to 'stripe'
$plan = isset($data['plan']) ? $data['plan'] : ''; // Default to 'stripe'

$connection = Common::getDatabaseConnection();

$config = new Config();

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
    if (!empty($license_key)) {

        // Prepare the query to select `sk_key` and `sign_key` from the licensekey table
        $stmt = $connection->prepare("SELECT sk_key, sign_key FROM licensekey WHERE license_key = :license_key");
        // Execute the query with the provided license_key
        $stmt->execute([':license_key' => $license_key]);
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Output or use the retrieved keys as needed
        $client_id = $result['sk_key'] ?? null;
        $clientSecret =  $result['sign_key'] ?? null;

        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $client_id,     // Replace with your PayPal Client ID
                $clientSecret  // Replace with your PayPal Client Secret
            )
        );
        $apiContext->setConfig(['mode' => 'live']);
    } else {
        $bankKey = $config->apps[$appName][$bankName] ?? null;
        if ($bankKey != null) {
            $bankConfig = $config->banks[$bankKey];
            $client_id = $bankConfig['client_id'] ?? null;
            $clientSecret = $bankConfig['client_secret'] ?? null;
        }
    }
}

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



// $stripe_funtion = new StripeApiFunction($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
switch ($func) {
    case 'create-checkout-session':
        if (empty($license_key)) {
            // Nếu không có licenseKey, tạo URL chuyển trang

            $encodedPlan = base64_encode($plan);
            $encodedLicenseKey = base64_encode($license_key);
            $encodedappName = base64_encode($appName);
            $url = "http://localhost:8080/pay?licenseKey=" . urlencode($encodedLicenseKey) . "&planName=" . urlencode($encodedPlan) . "&appName=" . urlencode($encodedappName);

            // Trả về URL chuyển trang
            $response = [
                'session' => [
                    'url' => $url
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            $bank_name =  getBackNameByLicenseKey($license_key, $connection);
            var_dump($bank_name);
            $convertname = strtolower($bank_name);
            if ($convertname == 'stripe') {
                $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
                $stripeWebhook->updateSubStripe($license_key, $plan, $convertname);
            } else {
                $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
                $paypalWebhook->updateSubPaypal($license_key, $plan, $convertname);
            }
        }
        break;
    case 'create-pay-stripe':
        $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $stripeWebhook->createPaySessionStripe();
        //pp 
        break;
    case 'create-pay-paypal':
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->createPaySessionPaypal();
        //pp 
        break;

    case 'webhookStripe':
        $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $stripeWebhook->handleWebhook();
        break;



        //phần xử lý của paypal tạo gói

    case 'revise-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->reviseSubscription($access_token);
        break;
    case 'update-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->upSubscription($access_token);
        break;
    case 'status-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->getSubscriptionStatus($access_token);
        break;
    case 'list-subscription':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->listSubscriptions($access_token);
        break;
    case 'create-product':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->createProduct($access_token);
        break;
    case 'list-products':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->listProducts($access_token);
        break;
    case 'cancel-products':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->cancelSubscription($access_token);
        break;
    case 'delete-products':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->deleteProducts($access_token);
        break;
    case 'create-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->createPlans($access_token);
        break;
    case 'list-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->listPlans($access_token);
        break;
    case 'detail-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->getPlanDetails($access_token);
        break;
    case 'delete-plans':
        $access_token = get_paypal_access_token($client_id, $clientSecret);
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->deleteAllPlans($access_token);
        break;
        //end 
        //webhookpaypal
    case 'webhookPaypal':
        $paypalWebhook = new PaypalWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $paypalWebhook->handlePaypalWebhook();
        break;
        //end

    case 'check-subscription':
        $stripeWebhook = new StripeWebhook($name, $config->banks, $appName, $bankName, $config->apps, $license_key);
        $stripeWebhook->checkSubscription();
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
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
