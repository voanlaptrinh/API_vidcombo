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
    if (!$jsonData) {
        http_response_code(400); // Bad Request
        echo 'Invalid webhook data';
        return;
    }

    // Lấy ID của sự kiện để kiểm tra xem nó đã được xử lý chưa
    $eventId = $jsonData['id'];

    // Kiểm tra xem sự kiện đã được xử lý trước đó hay chưa
    if (isEventProcessed($eventId)) {
        http_response_code(200);
        echo 'Webhook event already processed';
        return;
    }

    // Xử lý sự kiện webhook từ PayPal
    $event_type = $jsonData['event_type'];
    $logFile = __DIR__ . '/log/event_type.log';
    error_log('event_type: ' . print_r($event_type, true) . PHP_EOL, 3, $logFile);
    switch ($event_type) {
        case 'BILLING.PLAN.CREATED':
            handleBillingPlanCreated($jsonData['resource']);
            break;
        case 'BILLING.SUBSCRIPTION.CREATED':
            handleBillingSubcriptionreated($jsonData['resource']);
         break;
        case 'BILLING.SUBSCRIPTION.ACTIVATED':
            handleBillingSubcriptionActivereated($jsonData['resource']);
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

if ($requestUri === '/v1/billing/subscriptions' && $method === 'POST') {
    handleCheckout();
} elseif ($requestUri === '/webhook/paypal' && $method === 'POST') {
    handlePaypalWebhook();
} else {
    http_response_code(404);
    echo '404 Not Found';
}


function handleBillingSubcriptionreated($subscription){
    $logFile = __DIR__ . '/log/subscription_dât.log';
    error_log('Response: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
}
function handleBillingSubcriptionActivereated($subscription){
    $logFile = __DIR__ . '/log/handleBillingSubcriptionActivereated.log';
    error_log('handleBillingSubcriptionActivereated: ' . print_r($subscription, true) . PHP_EOL, 3, $logFile);
}
// Hàm kiểm tra xem một sự kiện đã được xử lý trước đó hay chưa
function isEventProcessed($eventId)
{
    // Thực hiện các kiểm tra để xác định xem sự kiện đã được xử lý hay chưa
    // Ví dụ: kiểm tra trong cơ sở dữ liệu, bộ nhớ cache, hoặc tệp log
    // Trong ví dụ này, tôi giả sử rằng không có bất kỳ sự kiện nào được xử lý trước đó
    return false;
}

// Hàm đánh dấu một sự kiện đã được xử lý
function markEventAsProcessed($eventId)
{
    // Thực hiện các hành động để đánh dấu sự kiện đã được xử lý
    // Ví dụ: lưu trạng thái của sự kiện trong cơ sở dữ liệu hoặc tệp log
}




function handleBillingPlanCreated($planData)
{

    $planId = $planData['id'];
    $planStatus = $planData['status'];


    $logFile = __DIR__ . '/log/planData.log';
    error_log('Response: ' . print_r($planData, true) . PHP_EOL, 3, $logFile);
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
                "interval_unit" => "MONTH",
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

// function createSubscription($plan_id, $token) {
//     $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions";
//     $headers = [
//         "Content-Type: application/json",
//         "Authorization: Bearer $token"
//     ];
//     $data = [
//         "plan_id" => $plan_id,
//         "start_time" => date('c', strtotime('+1 day')),
//         "subscriber" => [
//             "name" => [
//                 "given_name" => "John",
//                 "surname" => "Doe"
//             ],
//             "email_address" => "customer@example.com"
//         ],
//         "application_context" => [
//             "brand_name" => "Your Brand",
//             "locale" => "en-US",
//             "shipping_preference" => "SET_PROVIDED_ADDRESS",
//             "user_action" => "SUBSCRIBE_NOW",
//             "payment_method" => [
//                 "payer_selected" => "PAYPAL",
//                 "payee_preferred" => "IMMEDIATE_PAYMENT_REQUIRED"
//             ],
//             "return_url" => "https://five-mails-join.loca.lt/return.php",
//             "cancel_url" => "https://five-mails-join.loca.lt/cancel.php"
//         ]
//     ];

//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//     curl_setopt($ch, CURLOPT_POST, 1);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//     $response = curl_exec($ch);
//     curl_close($ch);
//     $logFile = __DIR__ . '/log/subcription.log';
//     error_log('Response: ' . print_r($response, true) . PHP_EOL, 3, $logFile);
//     return json_decode($response);
// }











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
            'email_address' => 'khanh@gmai.com'
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

    if (isset($response->id)) {
        // Lưu thông tin subscription vào CSDL
        $subscriptionData = [
            'subscription_id' => $response->id,
            'plan_id' => $plan_id,
            'status' => $response->status,
            'create_time' => $response->create_time,
        ];
        saveSubscription($subscriptionData);
    }

    echo json_encode(['session' => $response]);
}


// // Hàm xử lý trang thanh toán
// function handleCheckout() {
//     $client_id = 'AaIx1Xou_zZlZ45b3lp1aPLZFZBhbHzwc9RcyceiSsvW3U8-Y01JkJ_j9NKh26ujdMc_8F597kxSRC9o'; // Thay thế bằng Client ID của bạn
//     $clientSecret = 'EHCBjJAnYrbaYUocnnJlVYmMsadaPge6no89WTrtTfd1NrpliaY109f6GE52HVsyDzbz6khXdf2C73DF';
//     $token = get_paypal_access_token($client_id, $clientSecret);
//     $product_id = 'PROD-1ES34532SK597535U';
//     $plan = createPlan($product_id, $token);
//     $plan_id = $plan->id;
//     createSubscription($plan_id, $token);

//     // Redirect hoặc hiển thị thông báo thanh toán thành công
//     header("Location: /success.php");
// }

// // Hàm xử lý webhook từ PayPal
// function handlePaypalWebhook()
// {
//     // Lấy nội dung của webhook từ request
//     $requestBody = file_get_contents('php://input');

//     // Kiểm tra chữ ký của webhook
//     $signature = $_SERVER['HTTP_PAYPAL_SIGNATURE'];

//     if (verifyPaypalWebhookSignature($requestBody, $signature)) {
//         // Chữ ký hợp lệ, giải mã nội dung webhook
//         $webhookData = json_decode($requestBody, true);
//         $logFile = __DIR__ . '/log/webhookData.log';
//         error_log('webhookData: ' . print_r($webhookData, true) . PHP_EOL, 3, $logFile);
//         // Xử lý các sự kiện từ webhook
//         switch ($webhookData['event_type']) {
//             case 'PAYMENT.CAPTURE.COMPLETED':
//                 handlePaymentCaptureCompleted($webhookData);
//                 break;
//                 // Thêm các case xử lý cho các sự kiện khác nếu cần
//             default:
//                 // Xử lý các sự kiện khác
//                 break;
//         }

//         // Trả về mã trạng thái 200 OK để PayPal biết rằng webhook đã được xử lý thành công
//         http_response_code(200);
//     } else {
//         // Trả về mã lỗi 401 Unauthorized nếu chữ ký không hợp lệ
//         http_response_code(401);
//     }
// }


// // Hàm xử lý sự kiện thanh toán hoàn tất
// function verifyPaypalWebhookSignature($requestBody, $signature)
// {
//     // Lấy Client ID và Client Secret từ biến môi trường hoặc từ một nguồn đáng tin cậy khác
//     $clientId = 'ATAwcPBvJAz5zlqv2tILRRyzOF1VkBC6yio-PmjeFvmX0HVZFjAi3fECgC7MkFknb-nAGSgUk_we0d8p';
//     $clientSecret = 'EFpmH487Fi-ZHq6jOmhpSHGJ2o_KEn8EyRGzOUU4mz1u8GPgtC0eSN9KQUROJNZDhxY2HS7vMcmVcX0u';
//     // Tạo chuỗi để kiểm tra chữ ký
//     $verificationString = $requestBody . $clientId;
//     $logFile = __DIR__ . '/log/verificationString.log';
//     error_log('verificationString: ' . print_r($verificationString, true) . PHP_EOL, 3, $logFile);
//     // Tạo chữ ký từ chuỗi và Client Secret
//     $calculatedSignature = base64_encode(hash_hmac('sha256', $verificationString, $clientSecret, true));

//     // So sánh chữ ký được tính toán với chữ ký gửi từ PayPal
//     return hash_equals($calculatedSignature, $signature);
// }

// function handlePaymentCaptureCompleted($webhookData)
// {
//     $logFile = __DIR__ . '/log/webhookData.log';
//     error_log('webhookData: ' . print_r($webhookData, true) . PHP_EOL, 3, $logFile);

// }





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


// function createPlan($product_id, $token)
// {
//     // PayPal API endpoint for creating billing plans
//     $token_url = "https://api.sandbox.paypal.com/v1/billing/plans";

//     $data = [
//         'product_id' => $product_id,
//         'name' => 'Sample Plan',
//         'description' => 'This is a sample billing plan for testing purposes.',
//         'status' => 'ACTIVE',
//         'billing_cycles' => [
//             [
//                 'frequency' => [
//                     'interval_unit' => 'MONTH',
//                     'interval_count' => 1
//                 ],
//                 'tenure_type' => 'REGULAR',
//                 'sequence' => 1,
//                 'total_cycles' => 12,
//                 'pricing_scheme' => [
//                     'fixed_price' => [
//                         'value' => '10.00',
//                         'currency_code' => 'USD'
//                     ]
//                 ]
//             ]
//         ],
//         'payment_preferences' => [
//             'auto_bill_outstanding' => true,
//             'setup_fee' => [
//                 'value' => '0.00',
//                 'currency_code' => 'USD'
//             ],
//             'setup_fee_failure_action' => 'CONTINUE',
//             'payment_failure_threshold' => 3
//         ],
//         'taxes' => [
//             'percentage' => '0',
//             'inclusive' => false
//         ]
//     ];

//     $logFile = __DIR__ . '/log/create_plan.log';
//     error_log('Request Data: ' . print_r($data, true) . PHP_EOL, 3, $logFile);

//     // Convert data to JSON format
//     $json_data = json_encode($data);

//     // Initialize cURL session
//     $ch = curl_init();

//     curl_setopt($ch, CURLOPT_URL, $token_url);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         'Content-Type: application/json',
//         'Authorization: Bearer ' . $token,
//     ]);

//     // Execute the cURL request
//     $result = curl_exec($ch);

//     // Check for cURL errors
//     if (curl_errno($ch)) {
//         echo 'Error:' . curl_error($ch);
//         exit();
//     }

//     // Decode the JSON response
//     $response = json_decode($result);

//     // Check for errors in the PayPal API response
//     if (isset($response->error)) {
//         echo 'Error: ' . $response->error_description;
//         exit();
//     }

//     // Close cURL session
//     curl_close($ch);

//     // Log the response
//     error_log('Response: ' . print_r($response, true) . PHP_EOL, 3, $logFile);

//     // Return the response (you may handle this differently based on your requirements)
//     return $response;
// }

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

// Function to obtain OAuth 2.0 token
// function get_paypal_access_token($client_id, $clientSecret)
// {
//     $token_url = "https://api.sandbox.paypal.com/v1/oauth2/token";

//     $ch = curl_init();
//     curl($ch, $token_url);
//     $result = curl_exec($ch);

//     if (curl_errno($ch)) {
//         echo 'Error:' . curl_error($ch);
//         exit();
//     }

//     $response = json_decode($result);
//     if (isset($response->error)) {
//         echo 'Error:' . $response->error_description;
//         exit();
//     }

//     curl_close($ch);
//     $access_token = $response->access_token;
//     $logFile = __DIR__ . '/log/refund_webhook.log';
//     error_log('$response->access_token: ' . $access_token . PHP_EOL, 3, $logFile);

//     return $access_token;
// }


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
