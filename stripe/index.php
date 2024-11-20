<?php
require_once '../redis.php';
require_once '../common.php';
require_once '../vendor/autoload.php';

use Stripe\Stripe;
use PHPMailer\PHPMailer\Exception;

// Kiểm tra URL và gọi hàm tương ứng


// // Log and check the parsed data
// $appName = isset($data['app_name']) ? $data['app_name'] : 'vidcombo';

// $name = isset($_GET['name']) ? trim($_GET['name']) : '';





// $paypalSecret = Common::getPaypalSecrets($appName, $name);
// if ($paypalSecret) {
//     $client_id = $paypalSecret['client_id'];
//     $clientSecret = $paypalSecret['client_secret'];
//     $webhookId = $paypalSecret['webhook_id'];


//     $apiContext = new \PayPal\Rest\ApiContext(
//         new \PayPal\Auth\OAuthTokenCredential(
//             $paypalSecret['client_id'],     // Replace with your PayPal Client ID
//             $paypalSecret['client_secret']  // Replace with your PayPal Client Secret
//         )
//     );
//     $apiContext->setConfig(['mode' => 'live']);
// }



$body = file_get_contents('php://input');
parse_str($body,  $data);
$appName = isset($data['app_name']) ? $data['app_name'] : 'vidcombo';
$bankName = isset($data['bank_name']) ? $data['bank_name'] : '';

$name = isset($_GET['name']) ? trim($_GET['name']) : ''; // Default to 'stripe'



error_log('app NAme ' . $appName);
// Check if the appName exists in the configuration
if (!empty($name)) {
    if (!isset($banks[$name])) {
        throw new Exception("Invalid bank name: {$name}");
    }

    $bankConfig = $banks[$name];
    error_log("Using bank configuration for name: {$name}");
    error_log(print_r($bankConfig, true));

    $client_id = $bankConfig['client_id'] ?? null;
    $clientSecret = $bankConfig['client_secret'] ?? null;

} else {
    // Nếu `name` không tồn tại, dùng `appName` và `bankName`
    if (!empty($appName)) {
        if (!isset($apps[$appName])) {
            throw new Exception('Invalid app name.');
        }

        $bankKey = $apps[$appName][$bankName] ?? null;

        if (!isset($banks[$bankKey])) {
            throw new Exception("Invalid bank key for app: {$appName} and bank: {$bankName}");
        }

        $bankConfig = $banks[$bankKey];
        error_log("Using bank configuration for appName: {$appName}, bankName: {$bankName}");
        error_log(print_r($bankConfig, true));

        $client_id = $bankConfig['client_id'] ?? null;
        $clientSecret = $bankConfig['client_secret'] ?? null;
    } else {
        throw new Exception('Both appName and name are empty.');
    }
}

$func = isset($_GET['func']) ? trim($_GET['func']) : '';



$stripe_funtion = new StripeApiFunction($name, $banks, $appName, $bankName, $apps);
switch ($func) {
    case 'create-checkout-session':
        $stripe_funtion->createCheckoutSession();
        break;
    case 'create-pay-stripe':
        $stripe_funtion->createPaySessionStripe();
        break;
    case 'check-subscription':
        $stripe_funtion->checkSubscription();
        break;

    case 'webhook':
        $stripe_funtion->handleWebhook();
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
}

class StripeApiFunction
{
    private $connection;
    private $apiKey;
    private $client_id;
    private $clientSecret;
    private $endpointSecret;
    private $access_token;
    private $plans_paypal;
    private $appName;
    private $name;
    private $banks;
    private $bankName;
    private $apps;
    private $app_name;
    private $plans;
    public $web_domain = 'https://www.vidcombo.com/';
    // Hàm khởi tạo
    function __construct($name, $banks, $appName, $bankName, $apps)
    {
        $this->banks = $banks;
        $this->appName = $appName;
        $this->bankName = $bankName;
        $this->name = $name;
        $this->apps = $apps;
        $this->init();
    }
    function init()
    {

        $paypalSecret = Common::getPaypalSecrets($this->appName, $this->name);


        if ($paypalSecret) {
            $this->client_id = $paypalSecret['client_id'];
            $this->clientSecret = $paypalSecret['client_secret'];
            $this->plans = json_decode($paypalSecret['plans'], true);
        }

        $stripeSecrets = Common::getStripeSecrets($this->appName, $this->name);
        if ($stripeSecrets) {
            $this->apiKey = $stripeSecrets['apiKey'];
            $this->endpointSecret = $stripeSecrets['endpointSecret'];
            $this->app_name = $stripeSecrets['app_name'];
            $this->plans = json_decode($stripeSecrets['plans'], true);
            Stripe::setApiKey($this->apiKey);
        } else {
            throw new Exception('No active Stripe secrets found.');
        }
        $this->connection = Common::getDatabaseConnection();
        // $this->plans = Common::$plans;
        if (!$this->connection) {
            throw new Exception('Database connection could not be established.');
        }
        $this->access_token = $this->get_paypal_access_token($this->client_id, $this->clientSecret);
    }

    private function get_paypal_access_token($client_id, $clientSecret)
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
        if ($result->access_token) {
            return $result->access_token;
        } else {
            error_log('null access token');
        }
    }

    function createCheckoutSession()
    {
        $body = file_get_contents('php://input');
        parse_str($body, $data);

        $licenseKey = isset($data['license_key']) ? $data['license_key'] : '';
        $plan = isset($data['plan']) ? $data['plan'] : 'plan1';

        $planKey = isset($this->plans[$plan]) ? $this->plans[$plan] : '';

        $planKeyPaypal = isset($this->plans_paypal[$plan]) ? $this->plans_paypal[$plan] : '';
        $appName = isset($data['app_name']) ? $data['app_name'] : 'vidcombo';
        if (empty($licenseKey)) {
            // Nếu không có licenseKey, tạo URL chuyển trang
            $encodedPlanKey = base64_encode($planKey);
            $encodedPlan = base64_encode($plan);
            $encodedLicenseKey = base64_encode($licenseKey);
            $encodedappName = base64_encode($appName);
            $url = "http://localhost:8080/pay?planKey=" . urlencode($encodedPlanKey) . "&licenseKey=" . urlencode($encodedLicenseKey) . "&planName=" . urlencode($encodedPlan) . "&appName=" . urlencode($encodedappName);

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
            $bank_name =  $this->getBackNameByLicenseKey($licenseKey);

            if ($bank_name == 'Stripe') {
                // Nếu có licenseKey, tìm subscriptionId và nâng cấp subscription
                $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey);

                if ($subscriptionId) {
                    // Nếu có subscriptionId, nâng cấp subscription hiện tại
                    $subscription = \Stripe\Subscription::retrieve($subscriptionId);

                    $updatedSubscription = \Stripe\Subscription::update($subscriptionId, [
                        'items' => [
                            [
                                'id' => $subscription->items->data[0]->id,
                                'price' => $planKey,
                            ],
                        ],
                        'proration_behavior' => 'create_prorations',
                    ]);

                    // Trả về thông tin subscription đã được cập nhật
                    header('Content-Type: application/json');
                    echo json_encode(['subscription' => $updatedSubscription]);
                    exit();
                } else {
                    // Nếu không tìm thấy subscriptionId, trả về lỗi hoặc thông báo
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'No subscription found for the provided license key.']);
                    exit();
                }
            } else {
                $body = file_get_contents('php://input');
                parse_str($body, result: $data);
                $licenseKey = isset($data['license_key']) ? $data['license_key'] : '';
                $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey);
                var_dump($this->access_token);
                $url = "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscriptionId}/revise";

                // Dữ liệu để cập nhật gói
                $reviseData = [
                    "plan_id" => $planKeyPaypal,
                    "application_context" => [
                        "brand_name" => "RIVERNET",
                        "locale" => "en-US",
                        "shipping_preference" => "NO_SHIPPING",
                        "user_action" => "SUBSCRIBE_NOW",
                        "return_url" =>  $this->web_domain . "paypal/success",
                        "cancel_url" =>  $this->web_domain
                    ]
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: Bearer {$this->access_token}"
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reviseData));

                $response = curl_exec($ch);
                curl_close($ch);

                $responseArray = json_decode($response, true);

                // Log the response for debugging purposes
                // Kiểm tra xem 'links' có tồn tại và có chứa URL cần thiết không
                if (!empty($responseArray)) {
                    $response = [
                        'session' => [
                            'url' => $responseArray['links'][0]['href']
                        ]
                    ];
                    header('Content-Type: application/json');
                    echo json_encode($response);
                } else {
                    error_log($responseArray);
                }
            }
        }
    }

    function findSubscriptionIdByLicenseKey($licenseKey)
    {
        if (!$licenseKey || strlen($licenseKey) != 32)
            return null;

        // Chuẩn bị và thực hiện truy vấn
        $stmt = $this->connection->prepare("SELECT `subscription_id` FROM `licensekey` WHERE `license_key` = :license_key");
        $stmt->execute([':license_key' => $licenseKey]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (isset($device['subscription_id']) && $device['subscription_id'])
            return $device['subscription_id'];
        return null;
    }
    function getBackNameByLicenseKey($licenseKey)
    {
        try {
            // First query to get the subscription_id from the licensekey table
            $stmt =  $this->connection->prepare("SELECT subscription_id FROM licensekey WHERE license_key = :licenseKey");
            $stmt->execute([':licenseKey' => $licenseKey]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if a subscription_id was found
            if ($result && isset($result['subscription_id'])) {
                $subscriptionId = $result['subscription_id'];

                // Second query to get the back_name from the subscriptions table
                $stmt =  $this->connection->prepare("SELECT bank_name FROM subscriptions WHERE subscription_id = :subscriptionId");
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


    function createPaySessionStripe()
    {
        $body = file_get_contents(filename: 'php://input');
        parse_str($body, result: $data);
        $licenseKey = isset($data['license_key']) ? $data['license_key'] : '';
        $plan = isset($data['plan']) ? $data['plan'] : 'plan1';
        $planKey = isset($this->plans[$plan]) ? $this->plans[$plan] : '';
        if (!$planKey) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'New plan and customer are required']);
            return;
        }

        try {
            $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey);

            if ($subscriptionId) {
                // Nếu có subscriptionId, nâng cấp subscription hiện tại
                $subscription = \Stripe\Subscription::retrieve($subscriptionId);

                $updatedSubscription = \Stripe\Subscription::update($subscriptionId, [
                    'items' => [
                        [
                            'id' => $subscription->items->data[0]->id,
                            'price' => $planKey,
                        ],
                    ],
                    'proration_behavior' => 'create_prorations',
                ]);

                header('Content-Type: application/json');
                echo json_encode(['subscription' => $updatedSubscription]);
            } else {
                // Nếu không có subscriptionId, tạo một phiên Stripe Checkout mới
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price' => $planKey,
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    'success_url' => $this->web_domain . "success?session_id={CHECKOUT_SESSION_ID}",
                    'cancel_url' => $this->web_domain,
                ]);

                header('Content-Type: application/json');
                echo json_encode(['session' => $session]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    // Hàm kiểm tra subscription
    function checkSubscription()
    {
        $subscriptionId = Common::getString('subscriptionId');

        if (!$subscriptionId) {
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['error' => 'subscriptionId parameter is missing']);
            return;
        }

        // Retrieve the subscription from Stripe
        $subscription = \Stripe\Subscription::retrieve($subscriptionId);

        // Extract necessary information from the subscription object
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;
        $items = $subscription->items->data;

        // Prepare the response data
        $response = [
            'status' => $status,
            'current_period_end' => date('Y-m-d H:i:s', $current_period_end),
            'items' => $items,
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    // Hàm xử lý webhook
    function handleWebhook()
    {
        $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $payload = @file_get_contents('php://input');

        error_log($this->app_name);
        $event = \Stripe\Webhook::constructEvent($payload, $sig, $this->endpointSecret);

        $fname = date('Y_m_d_H_i_s') . '.log';
        $request_data = json_encode($_REQUEST);
        $raw_input_data = json_encode(file_get_contents('php://input'));
        $server_data = json_encode($_SERVER);
        $data = $request_data . "\n" . $raw_input_data . "\n" . $server_data;
        // $log_directory = 'log/';
        // file_put_contents($log_directory . $fname, $data);

        try {
            switch ($event->type) {
                case 'invoice.payment_succeeded': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;
                case 'invoice.finalized':
                    $this->handleInvoiceFinalized($event->data->object);
                    break;
                case 'invoice.updated':
                    $invoice = $event->data->object; // Lấy đối tượng hóa đơn từ sự kiện
                    $this->handleInvoiceUpdated($invoice);
                    break;
                case 'invoice.created':
                    $invoice = $event->data->object; // Lấy đối tượng hóa đơn từ sự kiện
                    $this->handleInvoiceCreated($invoice);
                    break;
                case 'invoice.paid':
                    $invoice = $event->data->object; // Lấy đối tượng hóa đơn từ sự kiện
                    $this->handleInvoicePaid($invoice);
                    break;
                case 'invoice.payment_failed': // Khi thanh toán hóa đơn không thành công
                    $this->handleSubscriptionExpired($event->data->object);
                    break;
                case 'invoiceitem.created': // invoice created
                    $this->handleinvoiceitem($event->data->object);
                    break;
                case 'customer.subscription.created': // Khi một đăng ký mới được tạo
                    $this->handleSubscriptionCreated($event->data->object, $this->app_name);
                    break;
                case 'customer.subscription.updated': // Khi một đăng ký được cập nhật
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                case 'customer.subscription.deleted': // Khi một đăng ký được bị hủy haowjc kết thúc
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;
                case 'customer.created': // Khi một khách hàng mới được tạo
                    $this->handleCustomerUpdated($event->data->object);
                    break;
                case 'customer.updated':
                    $this->handleCustomerUpdated($event->data->object);
                    break;
                case 'charge.refunded':
                    $this->handleRefund($event->data->object);
                    break;

                default:
                    // error_log('Unhandled event type ' . $event->type);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    function handleinvoiceitem($invoice)
    {
        // $logFile = __DIR__ . '/logs/handleinvoiceitem.log';
        // error_log($invoice . PHP_EOL, 3, $logFile);
        // error_log("Event:" . $invoice);
    }

    function handleRefund($refund)
    {
        // Lấy thông tin cần thiết từ hoàn tiền
        $amount_captured = $refund->amount_captured;
        $amount_refunded = $refund->amount_refunded;
        $customer_id = $refund->customer;
        $invoice_id = $refund->invoice;
        $payment_intent = $refund->payment_intent;
        $payment_method = $refund->payment_method;
        $receipt_url = $refund->receipt_url;
        $created_at = $refund->created;
        $created_date = date('Y-m-d H:i:s', $created_at);
        $stmt = $this->connection->prepare("SELECT `plan_alias` FROM `licensekey` WHERE `customer_id` = :customer_id");
        $stmt->execute([':customer_id' => $customer_id]);
        $plan_alias = $stmt->fetchColumn();

        $query = 'INSERT INTO `refund` (`amount_captured`, `amount_refunded`, `customer_id`, `invoice_id`, `payment_intent`, `payment_method`, `receipt_url`, `plan_alias`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$amount_captured, $amount_refunded, $customer_id, $invoice_id, $payment_intent, $payment_method, $receipt_url, $plan_alias, $created_date]);

        $updateQuery = 'UPDATE `licensekey` SET `status` = "inactive" WHERE customer_id = ?';
        $updateStmt = $this->connection->prepare($updateQuery);
        $updateStmt->execute([$customer_id]);
        //        error_log('update licensekey' . $refund);
    }

    function handleCustomerUpdated($customer)
    {
        // Lấy thông tin từ đối tượng khách hàng
        $customer_id = $customer->id;
        $email = $customer->email ?: '';
        $name = $customer->name ?: '';
        $created_at = $customer->created;
        $created_date = date('Y-m-d H:i:s', $created_at);

        // Chuẩn bị truy vấn SQL để chèn hoặc cập nhật khách hàng vào cơ sở dữ liệu
        $query = 'INSERT INTO `customers` (`customer_id`, `email`, `name`, `created_at`) VALUES (?, ?, ?, ?)  
    ON DUPLICATE KEY UPDATE `email` = "' . $email . '",  `name` = "' . $name . '", `created_at` = "' . $created_date . '"';
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$customer_id, $email, $name, $created_date]);

        error_log('Subscription inserted');
    }

    function handleInvoiceUpdated($invoice)
    {
        // Thực hiện logic cập nhật thông tin hóa đơn ở đây
        // Ví dụ: chèn hóa đơn vào cơ sở dữ liệu

        $invoice_id = $invoice->id;
        $customer_id = $invoice->customer;
        $amount_paid = $invoice->amount_paid;
        $status = $invoice->status;
        $subscription_id = $invoice->subscription;
        $amount_due = $invoice->amount_due;
        $created = $invoice->created;
        $customer_email = $invoice->customer_email;

        $invoice_date = date('Y-m-d H:i:s', $invoice->created);
        $pre_end = $invoice->lines['data'][0]['period']['end'];
        $plan_id = $invoice->lines['data'][0]['plan']['id'];
        $period_end = $pre_end;
        $invoiced_date = date('Y-m-d', $invoice->created);
        $customer_name = $invoice->customer_name;

        try {
            // Cập nhật thông tin trong bảng invoice
            $sql = $this->connection->prepare("UPDATE `invoice` SET `status` = :status, `subscription_id` = :subscription_id, `customer_id` = :customer_id, `amount_paid`= :amount_paid, `period_end`= :period_end WHERE `invoice_id` = :invoice_id");
            $sql->execute([
                ':status' => $status,
                ':subscription_id' => $subscription_id,
                ':customer_id' => $customer_id,
                ':amount_paid' => $amount_paid,
                ':invoice_id' => $invoice_id,
                ':period_end' => $period_end
            ]);

            // Chỉ cập nhật bảng licensekey nếu trạng thái là 'paid'
            if ($status == 'paid') {
                $amount_in_dollars = $amount_due / 100;
                $amount_due =  number_format($amount_in_dollars, 2);
                // Gửi email thông báo
                // Create an instance of PHPMailer
                if ($this->app_name == 'vidcombo') {
                    Common::sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date);
                } else {
                    Common::sendSuccessEmailVidobo($customer_email, $customer_name, $amount_due, $invoiced_date);
                }
                
              
            }
        } catch (PDOException $e) {
            // Ghi lại lỗi nếu có vấn đề với cơ sở dữ liệu
            error_log("Database error: " . $e->getMessage());
        } catch (Exception $e) {
            // Ghi lại các lỗi khác
            error_log("General error: " . $e->getMessage());
        }
    }

    function handleSubscriptionExpired($invoice)
    {
        $customer = $invoice->customer;
        $subscription_id = $invoice->subscription;
        $status = $invoice->status;

        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` = :status WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':status' => $status
        ]);
        error_log("Subscription expired for customer: $customer, subscription ID: $subscription_id");
    }
    function handleInvoiceCreated($invoice)
    {
        $invoiceId = $invoice->id;
        $customerId = $invoice->customer;
        $amountDue = $invoice->amount_due;
        $currency = $invoice->currency;
        $status = $invoice->status;
        $customer_email = $invoice->customer_email;
        $subscription_id = $invoice->subscription;
        // error_log("Invoice created for customer" . $invoice);
    }

    function handleInvoicePaid($invoice)
    {
        // Chuyển $invoice thành một mảng
        $invoice_array = [
            'subscription' => $invoice->subscription,
            'status' => $invoice->status,
            'subtotal' => $invoice->subtotal,
            'lines' => [
                'data' => $invoice->lines['data']
            ],
            'customer_name' => $invoice->customer_name
        ];

        //Lất các thông tin liên quán
        $subscription_id = $invoice_array['subscription'];
        $last_index = count($invoice_array['lines']['data']) - 1;
        $last_line_item = $invoice_array['lines']['data'][$last_index];
        $status_invoice = $invoice_array['status'];
        $subtotal_invoice = $invoice_array['subtotal'];

        // Lấy giá trị period end từ phần tử cuối cùng
        $pre_end = $last_line_item['period']['end'];
        $period_end = date('Y-m-d H:i:s', $pre_end);

        // Lấy plan và customer_name từ mảng
        $plan = $last_line_item['plan']['id'];
        $customer_name = $invoice_array['customer_name'];

        // Tìm plan_name
        $plan_name = array_search($plan, $this->plans);

        // Cập nhật invoice trong cơ sở dữ liệu
        $stmt = $this->connection->prepare("UPDATE `invoice` SET `status` = :status_invoice, `amount_due` = :subtotal_invoice WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':status_invoice' => $status_invoice,
            ':subtotal_invoice' => $subtotal_invoice,
            ':subscription_id' => $subscription_id,

        ]);

        // Cập nhật licensekey trong cơ sở dữ liệu
        $stmt = $this->connection->prepare("UPDATE `licensekey` SET `current_period_end` = :current_period_end, `plan` = :plan, `plan_alias` = :plan_alias, `status`=:status WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':current_period_end' => $period_end,
            ':plan' => $plan,
            ':plan_alias' => $plan_name,
            ':status' => 'active',
            ':subscription_id' => $subscription_id,
        ]);

        // Cập nhật redis cache
        $stmt = $this->connection->prepare("SELECT * FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        $stmt->execute([':subscription_id' => $subscription_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $license_key = isset($result['license_key']) ? $result['license_key'] : '';

        if ($license_key) {
            $redis = new RedisCache('DETAIL_' . $license_key);
            $redis->setCache('', 300);

            // Gửi email nếu license_key tồn tại và chưa được gửi
            if ($result['send'] === 'not') {
                // Lấy email khách hàng
                $customer_email = $this->getCustomerEmailBySubscriptionId($subscription_id);
                if ($this->app_name == 'vidcombo') {
                    $resu = Common::sendLicenseKeyEmail($customer_email, $customer_name, $license_key);
                } else {
                    $resu = Common::sendLicenseKeyEmailVidobo($customer_email, $customer_name, $license_key);
                }

                // Gửi licenseKey qua email

                if ($resu) {
                    $licensekey_stmt = $this->connection->prepare("UPDATE `licensekey` SET `send` = :send WHERE `license_key` = :license_key");
                    $licensekey_stmt->execute([
                        ':send' => 'ok',
                        ':license_key' => $license_key
                    ]);
                }
            } else {
                error_log("No license key found for subscription ID: $subscription_id");
            }
        }
    }

    // Hàm để xử lý khi thanh toán invoice thành công
    function handleInvoicePaymentSucceeded($invoice)
    {
        if (empty($invoice) || empty($invoice->subscription)) {
            error_log('Invalid invoice data');
            return;
        }

        // Lấy thông tin cần thiết từ invoice
        $subscription_id = $invoice->subscription;

        // Lấy thông tin chi tiết của subscription
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;

        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn
        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` = :status, `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id ");
        if (!$stmt) {
            throw new Exception('Query preparation failed');
        }
        $stmt->execute([
            ':status' => $status,
            ':current_period_end' => $current_period_end_date,
            ':subscription_id' => $subscription_id,
        ]);

        /*Cập nhật status của key*/

        /*Nếu chưa gửi key -> gửi*/

        /*Gửi email thanh toán thành công*/
    }

    // Hàm để xử lý khi một subscription mới được tạo
    function handleSubscriptionCreated($subscription, $appName)
    {
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $current_period_start = $subscription->current_period_start;
        $current_period_end = $subscription->current_period_end;
        $customer = $subscription->customer;
        $plan = $subscription->plan->id;
        $current_period_start_date = date('Y-m-d H:i:s', $current_period_start);
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

        $stmt = $this->connection->prepare("INSERT INTO `subscriptions` (`app_name`, `customer_id`, `subscription_id`, `status`, `current_period_start`, `current_period_end`, `customer`, `subscription_json`, `plan`, `bank_name`) VALUES (:app_name, :customer_id, :subscription_id, :status, :current_period_start, :current_period_end, :customer, :subscription_json, :plan, :bank_name)");
        $stmt->execute([
            ':app_name' => $appName,
            ':customer_id' => $customer,
            ':subscription_id' => $subscription_id,
            ':status' => $status,
            ':current_period_start' => $current_period_start_date,
            ':current_period_end' => $current_period_end_date,
            ':customer' => $customer,
            ':subscription_json' => $subscription,
            ':plan' => $plan,
            ':bank_name' => 'Stripe'
        ]);

        /*Tạo key*/
        $licenseKey = $this->generateLicenseKey();
        $plan_alias = array_search($plan, $this->plans);

        $stmt2 = $this->connection->prepare("INSERT INTO `licensekey` (`customer_id`, `status`, `subscription_id`, `license_key`, `send`, `plan`, `plan_alias`, `sk_key`, `sign_key`, `created_at`) VALUES (:customer_id, :status, :subscription_id, :license_key, :send, :plan, :plan_alias, :sk_key, :sign_key, :created_at)");
        $stmt2->execute([
            ':customer_id' => $customer,
            ':subscription_id' => $subscription_id,
            ':license_key' => $licenseKey,
            ':status' => 'inactive',
            ':send' => 'not',
            ':plan' => $plan,
            ':plan_alias' => $plan_alias,
            ':sk_key' => $this->apiKey,
            ':sign_key' => $this->endpointSecret,
            ':created_at' => date('Y-m-d H:i:s')
        ]);

        error_log("Subscription created for customer: $customer, subscription ID: $subscription_id, status: $status, current period start: $current_period_start_date, current period end: $current_period_end_date");
    }

    function getCustomerEmailBySubscriptionId($subscription_id)
    {
        try {
            $stmt = $this->connection->prepare("SELECT `customer_email` FROM `invoice` WHERE `subscription_id` = :subscription_id");
            $stmt->execute([':subscription_id' => $subscription_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($result['customer_email']) ? $result['customer_email'] : null;
        } catch (PDOException $e) {
            error_log("Error occurred in getCustomerEmailBySubscriptionId: " . $e->getMessage());
            return null;
        }
    }

    function handleInvoiceFinalized($invoice)
    {
        // Lấy thông tin từ đối tượng hóa đơn
        $invoice_array = [
            'lines' => [
                'data' => $invoice->lines['data']
            ],
        ];
        $last_index = count($invoice_array['lines']['data']) - 1;
        $last_line_item = $invoice_array['lines']['data'][$last_index];


        $invoice_id = $invoice->id;
        $amount_paid = $invoice->amount_paid;
        $currency = $invoice->currency;
        $status = $invoice->status;
        $customer_email = $invoice->customer_email;
        $payment_intent = $invoice->payment_intent;
        // $amount_due = $invoice->amount_due;
        $amount_due =  $last_line_item['plan']['amount'];
        $created = $invoice->created;
        $period_end = $last_line_item['period']['end'];
        $period_start = $last_line_item['period']['start'];
        $subscription_id = $invoice->subscription;
        $customer_id = $invoice->customer;
        $customer_name = $invoice->customer_name;

        $invoice_date = date('Y-m-d H:i:s', $invoice->created);
        $invoiced_date = date('Y-m-d', $invoice->created);

        // Kiểm tra xem licenseKey tồn tại trong bảng licensekey
        // Sử dụng Prepared Statement để tránh tấn công SQL injection
        $stmt = $this->connection->prepare("INSERT INTO `invoice` (`invoice_id`, `amount_paid`, `currency`, `status`, `invoice_datetime`, `customer_email`, `payment_intent`, `amount_due`, `created`, `period_end`, `period_start`, `subscription_id`, `customer_id`) VALUES (:invoice_id, :amount_paid, :currency, :status, :invoice_datetime, :customer_email, :payment_intent, :amount_due, :created, :period_end, :period_start, :subscription_id, :customer_id)");
        $stmt->execute([
            ':invoice_id' => $invoice_id,
            ':status' => $status,
            ':amount_paid' => $amount_paid,
            ':currency' => $currency,
            ':customer_email' => $customer_email,
            ':payment_intent' => $payment_intent,
            ':amount_due' => $amount_due,
            ':created' => $created,
            ':period_end' => $period_end,
            ':period_start' => $period_start,
            ':invoice_datetime' => $invoice_date,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer_id
        ]);

        // Cập nhật customer_email vào bảng subscriptions
        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `customer_email` = :customer_email WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':customer_email' => $customer_email,
            ':subscription_id' => $subscription_id
        ]);




        $stmt = $this->connection->prepare("SELECT `license_key`, `send` FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        $stmt->execute([':subscription_id' => $subscription_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Kiểm tra và gửi email chỉ khi licenseKey tồn tại

        if ($result && isset($result['license_key']) && $result['send'] === 'not') {
            $licenseKey = $result['license_key'];

            error_log("EMAIL: $customer_email");
            error_log("licensekey: $licenseKey");

            //Gửi licenseKey qua email
            $send_status = Common::sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey);

            if ($send_status) {
                $licensekey_stmt = $this->connection->prepare("UPDATE `licensekey` SET `send` = :send WHERE `subscription_id` = :subscription_id");
                $licensekey_stmt->execute([
                    ':send' => 'ok',
                    ':subscription_id' => $subscription_id
                ]);
            }
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }

        // Giá trị từ Stripe
        $amount_in_dollars = $amount_due / 100;
        $amount_due =  number_format($amount_in_dollars, 2);

        if ($status == 'paid') {

            if ($this->app_name == 'vidcombo') {
                Common::sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date);
            } else {
                Common::sendSuccessEmailVidobo($customer_email, $customer_name, $amount_due, $invoiced_date);
            }
            // Common::sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date);
        }
    }

    function handleSubscriptionUpdated($subscription)
    {
        // Lấy thông tin cần thiết từ subscription
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;
        $current_period_start = $subscription->current_period_start;

        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` = :status, `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':status' => $status,
            ':current_period_end' => $current_period_end_date,
            ':subscription_id' => $subscription_id,
        ]);
    }

    // Hàm để xử lý khi một subscription bị xóa
    function handleSubscriptionDeleted($subscription)
    {
        // Lấy thông tin cần thiết từ subscription
        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $period_end_subscription = date('Y-m-d H:i:s', $subscription->current_period_end);

        // Ghi log

        error_log("subscription: " . $subscription);

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn
        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` = :status, `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id AND `customer_id` = :customer_id");
        $stmt->execute([
            ':status' => $status,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer,
            ':current_period_end' => $period_end_subscription
        ]);

        // Lấy period_end từ licensekey
        $licensekey_stmt = $this->connection->prepare("SELECT `current_period_end` FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        $licensekey_stmt->execute([':subscription_id' => $subscription_id]);
        $licensekey = $licensekey_stmt->fetch(PDO::FETCH_ASSOC);
        $period_end_licensekey = $licensekey['current_period_end'];

        // So sánh period_end và cập nhật trạng thái nếu cần
        if ($period_end_subscription <= $period_end_licensekey) {
            $update_stmt = $this->connection->prepare("UPDATE `licensekey` SET `status` = :status WHERE `subscription_id` = :subscription_id");
            $update_stmt->execute([
                ':status' => 'inactive',
                ':subscription_id' => $subscription_id
            ]);
        }

        error_log("Subscription deleted for customer: $customer, subscription ID: $subscription_id");
    }

    // Hàm để tạo license key ngẫu nhiên
    function generateLicenseKey()
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }
}
