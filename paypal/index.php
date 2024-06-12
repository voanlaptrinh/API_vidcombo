<?php
require_once __DIR__ . '../../vendor/autoload.php';
require_once __DIR__ . '../../db.php';


// if ($requestUri === '/v1/catalogs/products' && $method === 'POST') {
//     createProduct($client_id, $clientSecret);
// } elseif ($requestUri === '/v1/billing/plans' && $method === 'POST') {
//     createPlan($product_id, $token);
// } elseif ($requestUri === '/v1/billing/list/plans' && $method === 'GET') {
//     listPlan($product_id, $token);
// } elseif ($requestUri === '/v1/billing/subscriptions' && $method === 'GET') {
//     createSubscription($plan_id, $token);
// } elseif ($requestUri === '/v1/get_paypal_access_token' && $method === 'POST') {
//     get_paypal_access_token($client_id, $clientSecret);
// }elseif ($requestUri === '/v1/webhooks/paypal' && $method === 'POST') {
//     handlePaypalWebhook($token);
// } else {
//     header("HTTP/1.1 404 Not Found");
//     echo '404 Not Found';
// }

// if ($requestUri === '/v1/billing/subscriptions' && $method === 'POST') {
//     handleCheckout();

// } elseif ($requestUri === '/webhook/paypal' && $method === 'POST') {
//     handlePaypalWebhook();
// } else {
//     http_response_code(404);
//     echo '404 Not Found';
// }


$client_id = 'Ad-yzXBkEiDu8EhXfwteI4UHxTjvWBNRw-XCPvK2tZLFZ_hq223HlrVaKGOKE4XOkg7oVM3amHaKP2w4'; // Thay thế bằng Client ID của bạn
$clientSecret = 'EGNNcs9bbgncSBIn16wBf7lFcAe35_oo8J7sHSgmsmLfxr9EFW03Q0xjqv7PU0iCRJT3Zv-1DNSfhvce'; // Thay thế bằng Client Secret của bạn
// Thiết lập các thông tin xác thực của ứng dụng PayPal
$webhookId = '88K966027J725123D'; // Webhook ID từ PayPal Developer Dashboard

// Hàm xử lý yêu cầu thanh toán
function handleCheckout()
{
    global $client_id, $clientSecret;

    $token = get_paypal_access_token($client_id, $clientSecret);
    $product_id = 'PROD-1ES34532SK597535U';
    $plan = createPlan($product_id, $token);
    $plan_id = $plan->id;
    createSubscription($plan_id, $token);

    // Redirect hoặc hiển thị thông báo thanh toán thành công
    header("Location: /success.php");
}

// Hàm xử lý webhook từ PayPal
function handlePaypalWebhook()
{
    // Lấy dữ liệu webhook gửi từ PayPal
    $webhookData = file_get_contents("php://input");
    $jsonData = json_decode($webhookData, true);
    $logFile = __DIR__ . '/log/'.date('Y-m-d H-i-s').'.log';
    file_put_contents($logFile, $webhookData);

    if (!$jsonData) {
        http_response_code(400); // Bad Request
        echo 'Invalid webhook data';
        return;
    }


    // Xử lý sự kiện webhook từ PayPal
    $event_type = $jsonData['event_type'];
    $logFile = __DIR__ . '/log/event_type.log';
    error_log('event_type: ' . print_r($event_type, true) . PHP_EOL, 3, $logFile);


    switch ($event_type) {

        case 'BILLING.SUBSCRIPTION.CREATED': //Khi gói sub thành công
            handleBillingSubcriptionreated($jsonData['resource']);
            break;
        case 'BILLING.SUBSCRIPTION.ACTIVATED': //Gói sub được active
            handleBillingSubcriptionActivereated($jsonData['resource']);
            break;
        case 'BILLING.SUBSCRIPTION.CANCELLED': //Xảy ra khi hủy gói
            handleBillingSubcriptionCancel($jsonData['resource']);
            break;
        case 'BILLING.PLAN.CREATED': //Hóa đơn được thêm vào
            handleBillingPlanCreated($jsonData['resource']);
            break;
        case 'BILLING.SUBSCRIPTION.EXPIRED': //Xảy ra khi ra hạn  thất bại
            handleBillingSubcriptionExpired($jsonData['resource']);
            break;
        case 'BILLING.SUBSCRIPTION.UPDATED':
            handleSubscriptionUpdated($jsonData['resource']);
            break;
        case 'BILLING.SUBSCRIPTION.RENEWED':
            handleSubscriptionUpdated($jsonData['resource']);
            break;
       
        default:
        
            echo 'Unhandled event type: ' . $event_type;
            break;
    }

    // Phản hồi về PayPal để xác nhận đã nhận được webhook
    http_response_code(200); // OK
    echo 'Webhook processed';
}

// Kiểm tra URL và gọi hàm xử lý tương ứng
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($requestUri === '/v1/billing/subscriptions' && $method === 'POST') { //Tạo mới một gói subscription
    handleCheckout();
} elseif ($requestUri === '/v1/billing/cancelSubscriptions' && $method === 'POST') {// HỦy gói subscription
    cancelSubscription();
} elseif ($requestUri === '/v1/billing/subscriptionsDetail' && $method === 'GET') { //Hiện ra chi tiết của gói sub 
    getPaypalSubscription();

}elseif($requestUri === '/v1/billing/subscriptionsTransactions' && $method === 'GET'){
    getPaypalSubscriptionTransactions();
} elseif ($requestUri === '/webhook/paypal' && $method === 'POST') {
    handlePaypalWebhook();
} else {
    http_response_code(404);
    echo '404 Not Found';
}


function getPaypalSubscriptionTransactions(){
    global $client_id, $clientSecret;
    // Lấy subscription_id từ phương thức GET
    $subscription_id = $_GET['subscription_id'];
    // Xác định URL API của PayPal
    $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}/transactions";
    $start_time = gmdate("Y-m-d\TH:i:s\Z", strtotime("-7 day"));
    $end_time = gmdate("Y-m-d\TH:i:s\Z", time());
    $url .= '?start_time='. urlencode($start_time).'&end_time='.urlencode($end_time);

    var_dump($url);
 
    // Lấy mã thông báo truy cập từ hàm get_paypal_access_token
    $token = get_paypal_access_token($client_id, $clientSecret);

    if (!$token) {
        echo "Could not retrieve access token.";
        return false;
    }

    // Khởi tạo một cURL handle
    $ch = curl_init();

    // Thiết lập các tùy chọn cho cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer {$token}",
        "Content-Type: application/json",
        "Accept: application/json",
    ));

    // Thực hiện yêu cầu GET
    $result = curl_exec($ch);

    // Kiểm tra nếu yêu cầu thành công
    if ($result === false) {
        curl_close($ch);
        return false;
    } else {
        // Giải mã kết quả JSON thành mảng PHP và trả về
        $json_result = json_decode($result, true);
        curl_close($ch);

        // Ghi log kết quả vào file log
        $logFile = __DIR__ . '/log/detailSubscription2.log';
        error_log('handleSubscriptionUpdated: ' . print_r($result, true) . PHP_EOL, 3, $logFile);

        return $json_result;
    }
}


function handleSubscriptionUpdated($subscription)
{
    $logFile = __DIR__ . '/log/handleSubscriptionUpdated.log';
    error_log('handleSubscriptionUpdated: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
}


function getPaypalSubscription()
{
    global $client_id, $clientSecret;
    // Lấy subscription_id từ phương thức GET
    $subscription_id = $_GET['subscription_id'];
    // Xác định URL API của PayPal
    $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}";
    
 
    // Lấy mã thông báo truy cập từ hàm get_paypal_access_token
    $token = get_paypal_access_token($client_id, $clientSecret);

    if (!$token) {
        echo "Could not retrieve access token.";
        return false;
    }

    // Khởi tạo một cURL handle
    $ch = curl_init();

    // Thiết lập các tùy chọn cho cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer {$token}"
    ));

    // Thực hiện yêu cầu GET
    $result = curl_exec($ch);

    // Kiểm tra nếu yêu cầu thành công
    if ($result === false) {
        curl_close($ch);
        return false;
    } else {
        // Giải mã kết quả JSON thành mảng PHP và trả về
        $json_result = json_decode($result, true);
        curl_close($ch);

        // Ghi log kết quả vào file log
        $logFile = __DIR__ . '/log/detailSubscription.log';
        error_log('handleSubscriptionUpdated: ' . print_r($json_result, true) . PHP_EOL, 3, $logFile);

        return $json_result;
    }
}




function handleBillingSubcriptionExpired($subscription)
{
    $logFile = __DIR__ . '/log/BILLING_SUBSCRIPTION_EXPIRED.log';
    error_log('BILLING_SUBSCRIPTION_EXPIRED: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
    $connection = getDatabaseConnection();

    $sql = $connection->prepare("UPDATE subscriptions SET status = :status, subscription_json = :subscription_json WHERE subscription_id = :subscription_id");
    $sql->execute([
        ':status' => $subscription['status'],
        ':subscription_id' => $subscription['id'],
        ':subscription_json' => $subscription,

    ]);
    $sql = $connection->prepare("UPDATE licensekey SET status = :status WHERE subscription_id = :subscription_id");
    $sql->execute([
        ':status' => 'inactive',
        ':subscription_id' => $subscription['id'],
    ]);
}

function handleBillingSubcriptionreated($subscription)
{
    $logFile = __DIR__ . '/log/BILLING_SUBSCRIPTION_CREATED.log';
    error_log('Response: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
    $connection = getDatabaseConnection();

    // Câu truy vấn SQL với các giá trị cần chèn
    $query = 'INSERT INTO subscriptions (subscription_id, plan, status, create_time, bank_name) VALUES (:subscription_id, :plan, :status, :create_time, :bank_name)';

    $stmt = $connection->prepare($query);

    // Liên kết các tham số với câu truy vấn
    $stmt->execute([
        ':subscription_id' => $subscription['id'],
        ':plan' => $subscription['plan_id'],
        ':status' => $subscription['status'],
        ':create_time' => $subscription['create_time'],
        ':bank_name' => 'Paypal'
    ]);

    // Đóng kết nối
    $stmt = null;
    $connection = null;
}
function generateLicenseKey()
{
    return bin2hex(random_bytes(16));
}
function handleBillingSubcriptionActivereated($subscription)
{
    $logFile = __DIR__ . '/log/handleBillingSubcriptionActivereated.log';
    error_log('handleBillingSubcriptionActivereated: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
    $licenseKey = generateLicenseKey();
    $connection = getDatabaseConnection();


    $sql = $connection->prepare("UPDATE subscriptions SET status = :status, subscription_json = :subscription_json WHERE subscription_id = :subscription_id");
    $sql->execute([
        ':status' => $subscription['status'],
        ':subscription_id' => $subscription['id'],
        ':subscription_json' => $subscription,

    ]);
    $stmt = $connection->prepare("INSERT INTO licensekey ( status, subscription_id, license_key) VALUES ( :status, :subscription_id, :license_key)");
    $stmt->execute([
        // ':customer_id' => $customer,
        ':subscription_id' => $subscription['id'],
        ':license_key' => $licenseKey,
        ':status' => 'active',
    ]);
    // Đóng kết nối
    $stmt = null;
    $connection = null;
}

function handleBillingSubcriptionCancel($subscription)
{
    $logFile = __DIR__ . '/log/TypeSubcriptionCancel.log';
    $connection = getDatabaseConnection();
    error_log('TypeSubcriptionCancel: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
    $sql = $connection->prepare("UPDATE subscriptions SET status = :status, subscription_json = :subscription_json WHERE subscription_id = :subscription_id");
    $sql->execute([
        ':status' => $subscription['status'],
        ':subscription_id' => $subscription['id'],
        ':subscription_json' => $subscription,

    ]);
    $sql = $connection->prepare("UPDATE licensekey SET status = :status WHERE subscription_id = :subscription_id");
    $sql->execute([
        ':status' => 'inactive',
        ':subscription_id' => $subscription['id'],
    ]);
}

function handleBillingPlanCreated($planData)
{



    $logFile = __DIR__ . '/log/planData.log';
    error_log('Response: ' . print_r($planData, true) . PHP_EOL, 3, $logFile);


    $connection = getDatabaseConnection();

    $currency = $planData['billing_cycles'][0]['pricing_scheme']['fixed_price']['currency_code'];
    $amount_due = $planData['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'];
    $date_string  = $planData['billing_cycles'][0]['pricing_scheme']['create_time'];
    $period_start = strtotime($date_string); // Chuyển đổi thành timestamp


    $interval_count = $planData['billing_cycles'][0]['frequency']['interval_count'];
    $interval_unit = $planData['billing_cycles'][0]['frequency']['interval_unit'];
    $period_end_timestamp = strtotime("+ $interval_count $interval_unit", $period_start);



    $logFile = __DIR__ . '/log/period_end_timestamp.log';
    error_log('period_end_timestamp: ' . print_r($period_end_timestamp, true) . PHP_EOL, 3, $logFile);
    // Câu truy vấn SQL với các giá trị cần chèn
    $query = 'INSERT INTO invoice (subscription_id, status, created, currency, amount_due, period_start, period_end) VALUES (:subscription_id, :status, :created, :currency, :amount_due, :period_start, :period_end)';

    $stmt = $connection->prepare($query);

    // Liên kết các tham số với câu truy vấn
    $stmt->execute([
        ':subscription_id' => $planData['id'],
        ':status' => $planData['status'],
        ':created' => $planData['create_time'],
        ':currency' => $currency,
        ':amount_due' => $amount_due,
        ':period_start' => $period_start,
        ':period_end' => $period_end_timestamp,
    ]);
    // Đóng kết nối
    $stmt = null;
    $connection = null;
}

function handlePaymentCaptureCompleted($webhookData)
{
    file_put_contents('webhook_log2.txt', json_encode($webhookData, JSON_PRETTY_PRINT), FILE_APPEND);
    http_response_code(200);
}

function get_paypal_access_token($client_id, $clientSecret)
{
    $url = "https://api.sandbox.paypal.com/v1/oauth2/token";
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

function createPlan($product_id, $token)
{
    $url = "https://api.sandbox.paypal.com/v1/billing/plans";
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $token"
    ];
    $data = [
        "product_id" => $product_id,
        "name" => "Basic Plan",
        "billing_cycles" => [[
            "frequency" => [
                "interval_unit" => "DAY",
                "interval_count" => 1
            ],
            "tenure_type" => "REGULAR",
            "sequence" => 1,
            "total_cycles" => 12,
            "pricing_scheme" => [
                "fixed_price" => [
                    "value" => "10",
                    "currency_code" => "USD"
                ]
            ]
        ]],
        "payment_preferences" => [
            "auto_bill_outstanding" => true,
            "setup_fee" => [
                "value" => "0",
                "currency_code" => "USD"
            ],
            "setup_fee_failure_action" => "CANCEL",
            "payment_failure_threshold" => 3
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
}

function createSubscription($plan_id, $token)
{
    $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions";
    $data = [
        'plan_id' => $plan_id,
        'start_time' => gmdate("Y-m-d\TH:i:s\Z", strtotime("+1 minute")), // Set start time to 1 minute in the future
        'subscriber' => [
            'name' => [
                'given_name' => 'John',
                'surname' => 'Doe'
            ],
            'email_address' => 'khanh@gmail.com'
        ],
        'application_context' => [
            'brand_name' => 'Your Brand',
            'locale' => 'en-US',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'SUBSCRIBE_NOW',
            'payment_method' => [
                'payer_selected' => 'PAYPAL',
                'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
            ],
            'return_url' => 'http://localhost:8080/return.php',
            'cancel_url' => 'http://localhost:8080/cancel.php'
        ]
    ];

    $json_data = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        exit();
    }

    $response = json_decode($result);
    curl_close($ch);
    $logFile = __DIR__ . '/log/subcription.log';
    error_log('subcription Data: ' . print_r($response, true) . PHP_EOL, 3, $logFile);
    return json_encode(['session' => $response]);
}

function cancelSubscription()
{
    // Kiểm tra yêu cầu và phương thức
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo '405 Method Not Allowed';
        return;
    }

    // Lấy dữ liệu từ yêu cầu POST
    $post_data = file_get_contents("php://input");
    parse_str($post_data, $post_params);

    // Lấy giá trị subscription_id từ mảng $post_params
    $subscription_id = $post_params['subscription_id'];
    global $client_id, $clientSecret;

    $token = get_paypal_access_token($client_id, $clientSecret);
    // Kiểm tra xem subscription_id và token có tồn tại không
    if (empty($subscription_id) || empty($token)) {
        http_response_code(400);
        echo '400 Bad Request - Missing subscription_id or token';
        return;
    }

    // Gửi yêu cầu hủy subscription tới PayPal
    $response = callPayPalCancelSubscriptionAPI($subscription_id, $token);

    // Xử lý kết quả trả về từ PayPal
    if ($response === true) {
        // Thực hiện các bước cần thiết trong hệ thống của bạn sau khi hủy subscription thành công
        // Ví dụ: cập nhật trạng thái subscription trong cơ sở dữ liệu của bạn
        http_response_code(200);
        echo 'Subscription cancelled successfully';
    } else {
        http_response_code(500);
        echo '500 Internal Server Error - Failed to cancel subscription';
    }
}

function callPayPalCancelSubscriptionAPI($subscription_id, $token)
{
    $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}/cancel";
    $data = [
        'reason' => 'USER_INITIATED_CANCEL'
    ];

    $json_data = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 204) {
        return true;
    } else {
        error_log('Error: Unexpected HTTP response code ' . $http_code);
        return false;
    }
}

function saveSubscription($subscriptionData)
{
    $connection = getDatabaseConnection();

    // Câu truy vấn SQL với các giá trị cần chèn
    $query = 'INSERT INTO subscriptions (subscription_id, plan, status, create_time, bank_name) VALUES (:subscription_id, :plan, :status, :create_time, :bank_name)';

    $stmt = $connection->prepare($query);

    // Liên kết các tham số với câu truy vấn
    $stmt->execute([
        ':subscription_id' => $subscriptionData['subscription_id'],
        ':plan' => $subscriptionData['plan_id'],
        ':status' => $subscriptionData['status'],
        ':create_time' => $subscriptionData['create_time'],
        ':bank_name' => 'Paypal'
    ]);
    // Đóng kết nối
    $stmt = null;
    $connection = null;
}

function listPlan($product_id, $token)
{
    // PayPal API endpoint for creating billing plans
    $token_url = "https://api.sandbox.paypal.com/v1/billing/plans";

    $data = [
        'product_id' => $product_id,
        'name' => 'Sample Plan',
        'description' => 'This is a sample billing plan for testing purposes.',
        'status' => 'ACTIVE',
        'billing_cycles' => [
            [
                'frequency' => [
                    'interval_unit' => 'MONTH',
                    'interval_count' => 1
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 12,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => '10.00',
                        'currency_code' => 'USD'
                    ]
                ]
            ]
        ],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'setup_fee' => [
                'value' => '0.00',
                'currency_code' => 'USD'
            ],
            'setup_fee_failure_action' => 'CONTINUE',
            'payment_failure_threshold' => 3
        ],
        'taxes' => [
            'percentage' => '0',
            'inclusive' => false
        ]
    ];

    // Ghi log cho dữ liệu gửi đi
    $logFile = __DIR__ . '/log/list_plan.log';
    error_log('Request Data: ' . print_r($data, true) . PHP_EOL, 3, $logFile);

    // Convert data to JSON format
    $json_data = json_encode($data);

    // Initialize cURL session
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    // Execute the cURL request
    $result = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        exit();
    }

    // Decode the JSON response
    $response = json_decode($result);

    // Check for errors in the PayPal API response
    if (isset($response->error)) {
        echo 'Error: ' . $response->error_description;
        exit();
    }

    // Close cURL session
    curl_close($ch);

    // Ghi log cho phản hồi
    error_log('Response list plan: ' . print_r($response, true) . PHP_EOL, 3, $logFile);

    // Return the response
    return $response->id;
}


function detailPlan($plan_id, $token)
{
    $token_url = "https://api-m.paypal.com/v1/billing/plans/";
}

function createProduct($client_id, $clientSecret)
{
    $token_url = "https://api.sandbox.paypal.com/v1/catalogs/products";

    // Data to create a product
    $data = [
        'name' => 'Sample Product',
        'description' => 'This is a sample product for testing purposes.',
        'type' => 'DIGITAL',
        'category' => 'SOFTWARE',
        'pricing_scheme' => [
            'fixed_price' => [
                'value' => '10.00',
                'currency_code' => 'USD'
            ]
        ]
    ];

    $json_data = json_encode($data);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($client_id . ':' . $clientSecret)
    ]);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        exit();
    }

    $response = json_decode($result);
    if (isset($response->error)) {
        echo 'Error:' . $response->error_description;
        exit();
    }

    curl_close($ch);

    // Log response to a file
    $logFile = __DIR__ . '/log/create_product.log';
    error_log('Response: ' . print_r($response->id, true) . PHP_EOL, 3, $logFile);
    return $response;
}



function curl($ch, $token_url, $json_data = 'grant_type=client_credentials')
{
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, 'ATAwcPBvJAz5zlqv2tILRRyzOF1VkBC6yio-PmjeFvmX0HVZFjAi3fECgC7MkFknb-nAGSgUk_we0d8p' . ":" . 'EFpmH487Fi-ZHq6jOmhpSHGJ2o_KEn8EyRGzOUU4mz1u8GPgtC0eSN9KQUROJNZDhxY2HS7vMcmVcX0u');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US',
        // 'Authorization: Basic ' . base64_encode('ATAwcPBvJAz5zlqv2tILRRyzOF1VkBC6yio-PmjeFvmX0HVZFjAi3fECgC7MkFknb-nAGSgUk_we0d8p' . ':' . 'EFpmH487Fi-ZHq6jOmhpSHGJ2o_KEn8EyRGzOUU4mz1u8GPgtC0eSN9KQUROJNZDhxY2HS7vMcmVcX0u')
    ]);
}
