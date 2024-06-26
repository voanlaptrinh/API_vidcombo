<?php
// require './';
require_once '../common.php';
require_once '../vendor/autoload.php';

use Stripe\Stripe;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

Stripe::setApiKey('sk_test_51OeDsPIXbeKO1uxjfGZLmBaoVYMdmbThMwRHSrNa6Zigu0FnQYuAatgfPEodv9suuRFROdNRHux5vUhDp7jC6nca00GbHqdk1Y');
define('ENDPOINT_SECRET', 'whsec_LbKCxrDhpvIqZf1iITZdbxA4z0tIxkhk');
// define('ENDPOINT_SECRET', 'whsec_5f17c8c4ada7dddedac39a07084388d087b1743d38e16af8bd996bb97a21c910');


$stripe_funtion = new StripeApiFunction();

// Kiểm tra URL và gọi hàm tương ứng
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$func = isset($_GET['func']) ? $_GET['func'] : '';


switch ($func) {
    case 'create-checkout-session':
        $stripe_funtion->createCheckoutSession();
        break;
    case 'check-subscription':
        $stripe_funtion->checkSubscription();
        break;
    case 'send-license-key':
        $stripe_funtion->sendLicenseKey(); // lấy ra trạng thái của key và key và địa chỉ mac
        break;
    case 'verify-license-key':
        $stripe_funtion->verifyLicenseKey();
        break;
    case 'email-subcription':
        $stripe_funtion->emailSubcription();
        break;
    case 'webhook':
        $stripe_funtion->handleWebhook();
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
}
// if ($requestUri === '/api/v1/create-checkout-session' && $method === 'POST') {
//     $stripe_funtion->createCheckoutSession();
// } elseif ($requestUri === '/api/v1/check-subscription' && $method === 'GET') {
//     $stripe_funtion->checkSubscription();
// } elseif ($requestUri === '/api/v1/send-license-key' && $method === 'POST') {
//     $stripe_funtion->sendLicenseKey();
// } elseif ($requestUri === '/api/v1/verify-license-key' && $method === 'POST') {
//     $stripe_funtion->verifyLicenseKey();
// } elseif ($requestUri === '/webhook' && $method === 'POST') {
//     $stripe_funtion->handleWebhook();
// } else {
//     header("HTTP/1.1 404 Not Found");
//     echo '404 Not Found';
// }

class StripeApiFunction
{
    private $connection;
    // Hàm khởi tạo
    function __construct()
    {
        $this->init();
    }
    function init()
    {
        $this->connection = Common::getDatabaseConnection();
        if (!$this->connection) {
            throw new Exception('Database connection could not be established.');
        }
    }
    public $web_domain = 'https://api.vidcombo.com';
    public $plans = array(
        '1month' => 'price_1PV2QfIXbeKO1uxjVvaZPb8p',
        '12month' => 'price_1PV2USIXbeKO1uxjnL1w3qPC',
        '6month' => 'price_1PV2VjIXbeKO1uxjHlOtM0oL'
    );


    function emailSubcription()
    {
        header('Content-Type: application/json');

        $email = isset($_GET['email']) ? $_GET['email'] : null;

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            return;
        }

        $conn =  $this->connection;

        if (!$conn) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection error']);
            return;
        }

        // Query to get subscription IDs from invoice table
        $query = "SELECT subscription_id FROM invoice WHERE customer_email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $subscription_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($subscription_ids)) {
            http_response_code(404);
            echo json_encode(['error' => 'No subscriptions found for this email']);
            return;
        }

        // Query to get subscription details from subscription table
        $query = "SELECT subscription_id, status, bank_name FROM subscriptions WHERE subscription_id IN (" . implode(',', array_map('intval', $subscription_ids)) . ")";
        $stmt = $conn->prepare($query);
        $stmt->execute();

        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['subscriptions' => $subscriptions]);
    }




    // Hàm tạo phiên Stripe Checkout
    function createCheckoutSession()
    {
        // Lấy dữ liệu từ yêu cầu
        $body = file_get_contents('php://input');
        parse_str($body, $data);


        $plan = $data['plan'] ?? null;

        // Tạo phiên Stripe Checkout
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $plan,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $this->web_domain . "/stripe/success.php?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => $this->web_domain . "/stripe/cancel.html",
        ]);


        // Gửi phản hồi JSON về phiên được tạo
        header('Content-Type: application/json');
        echo json_encode(['session' => $session]);
    }

    // Hàm kiểm tra subscription
    function checkSubscription()
    {
        $subscriptionId = isset($_GET['subscriptionId']) ? $_GET['subscriptionId'] : null;

        if ($subscriptionId === null) {
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


    // Hàm gửi license key
    function sendLicenseKey()
    {
        // $body = json_decode(file_get_contents('php://input'), true);
        // $email = $body['email'] ?? null;
        // $key = $body['key'] ?? null;


        // // Gửi email chứa license key
        // error_log("License key $key sent to $email");
        // header('Content-Type: application/json');
        // echo json_encode(['success' => true]);


        header("Content-Type: application/json");

        // Lấy địa chỉ IP của client
        $clientIP = $_SERVER['REMOTE_ADDR'];

        $geoInfo = @file_get_contents("http://ip-api.com/json/{$clientIP}?fields=status,country,countryCode");
        if ($geoInfo === FALSE) {
            $geoInfo = ["error" => "Không thể lấy thông tin địa lý"];
        } else {
            $geoInfo = json_decode($geoInfo, true);
        }

        if ($geoInfo['status'] === 'fail') {
            $countryCode = 'Không có thông tin quốc gia';
        } else {
            $countryCode = $geoInfo['countryCode'];
        }

        // Thông tin hệ điều hành của server
        $osInfo = php_uname();

        // Lấy tên máy chủ
        $hostname = gethostname();

        if (stristr(PHP_OS, 'win')) {
            $serverIP = gethostbyname($hostname);
        } else {
            $serverIP = shell_exec("hostname -I");
            $serverIP = trim($serverIP);
        }

        // Trích xuất tham số cpu và mac từ yêu cầu HTTP GET
        // $cpu = isset($_GET['cpu']) ? $_GET['cpu'] : 'Không có thông tin'; //CPU
        $mac = isset($_GET['mac']) ? $_GET['mac'] : 'Không có thông tin'; //Địa chỉ mác
        $operating = isset($_GET['operating']) ? $_GET['operating'] : 'Không có thông tin'; //Hệ điều hành

        if (!isset($_GET['mac']) || !isset($_GET['operating']) || !isset($_GET['license_key'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }



        $license_key = $_GET['license_key'];


        // Query the database
        $stmt = $this->connection->prepare("SELECT `license_key`, `status` FROM licensekey WHERE `license_key` = :license_key");
        $stmt->execute([':license_key' => $license_key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Return the response
            $response = [

                'license_key' => $result['license_key'],
                'status' => $result['status'],
                'error' => ''
            ];



            $sql = "INSERT INTO device (client_ip, geo, os, hostname, server_ip, mac, operating, license_key) 
            VALUES (:client_ip, :geo, :os, :hostname, :server_ip, :mac, :operating, :license_key)";

            // Thực thi truy vấn
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':client_ip', $clientIP);
            $stmt->bindParam(':geo', $countryCode);
            $stmt->bindParam(':os', $osInfo);
            $stmt->bindParam(':hostname', $hostname);
            $stmt->bindParam(':server_ip', $serverIP);
            $stmt->bindParam(':mac', $mac);
            $stmt->bindParam(':operating', $operating);
            $stmt->bindParam(':license_key', $license_key);

            // Thực thi truy vấn
            $stmt->execute();

            echo json_encode($response);
        } else {
            echo json_encode(['error' => 'License key not found']);
        }
    }

    // Hàm xác thực license key và cập nhật license key
    function verifyLicenseKey()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $email = $body['email'] ?? null;
        $key = $body['key'] ?? null;


        $query = 'SELECT * FROM subscriptions WHERE customer_email = ? AND license_key = ?';
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$email, $key]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($subscriptions) > 0) {
            $newLicenseKey = $this->generateLicenseKey();
            $updateQuery = 'UPDATE subscriptions SET license_key = ? WHERE customer_email = ?';
            $stmt = $this->connection->prepare($updateQuery);
            $stmt->execute([$newLicenseKey, $email]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false]);
        }
    }

    // Hàm xử lý webhook
    function handleWebhook()
    {
        $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $payload = @file_get_contents('php://input');

        $event = \Stripe\Webhook::constructEvent($payload, $sig, ENDPOINT_SECRET);

        $fname = date('Y_m_d_H_i_s') . '.log';

        $request_data = json_encode($_REQUEST);
        $raw_input_data = json_encode(file_get_contents('php://input'));
        $server_data = json_encode($_SERVER);

        $data = $request_data . "\n" . $raw_input_data . "\n" . $server_data;

        $log_directory = 'log/';

        file_put_contents($log_directory . $fname, $data);


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
                case 'invoice.payment_failed': // Khi thanh toán hóa đơn không thành công
                    $this->handleSubscriptionExpired($event->data->object);
                    break;
                case 'customer.subscription.created': // Khi một đăng ký mới được tạo
                    $this->handleSubscriptionCreated($event->data->object);
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
                    error_log('Unhandled event type ' . $event->type);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
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


        $query = 'INSERT INTO refund (amount_captured, amount_refunded, customer_id, invoice_id, payment_intent, payment_method, receipt_url) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$amount_captured, $amount_refunded, $customer_id, $invoice_id, $payment_intent, $payment_method, $receipt_url]);

        $updateQuery = 'UPDATE licensekey SET status = "inactive" WHERE customer_id = ?';
        $updateStmt = $this->connection->prepare($updateQuery);
        $updateStmt->execute([$customer_id]);
    }

    function handleCustomerUpdated($customer)
    {
        // Lấy thông tin từ đối tượng khách hàng
        $customer_id = $customer->id;
        $email = $customer->email ?? '';
        $name = $customer->name ?? '';
        $created_at = $customer->created;
        $created_date = date('Y-m-d H:i:s', $created_at);

        // Chuẩn bị truy vấn SQL để chèn hoặc cập nhật khách hàng vào cơ sở dữ liệu
        $query = 'INSERT INTO customers (customer_id, email, name, created_at) VALUES (?, ?, ?, ?)  
    ON DUPLICATE KEY UPDATE 
    email = VALUES(email), 
    name = VALUES(name), 
    created_at = VALUES(created_at)';
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

        $sql = $this->connection->prepare("UPDATE invoice SET status = :status, subscription_id = :subscription_id, customer_id = :customer_id, amount_paid= :amount_paid  WHERE invoice_id = :invoice_id");
        $sql->execute([
            ':status' => $status,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer_id,
            ':amount_paid' => $amount_paid,
            ':invoice_id' => $invoice_id

        ]);
        if ($status == 'paid') {
            $mail = new PHPMailer(true);

            //Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'thanbatbai3092002@gmail.com';  // Your Gmail address
            $mail->Password   = 'etejnwheciweprdo';  // Your Gmail password or app-specific password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;  // TCP port to connect to

            //Recipients
            $mail->setFrom('no-reply@example.com', 'Mailer');
            $mail->addAddress($customer_email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your invoice details';
            $mail->Body    = 
            "Dear $customer_email,<br>
            <br>Code Bill: $invoice_id<br>
            <br>Total amount paid: $amount_due $<br>
            <br>Date created: $invoice_date<br>
            <br>Thank you for your subscription! subid: " . $subscription_id;

            // Send the email
            $mail->send();
        }
        // Chuẩn bị truy vấn SQL để chèn hóa đơn vào cơ sở dữ liệu
        // $sql = "INSERT INTO invoice (invoice_id, customer_id, amount_paid, currency, status, invoice_date, customer_email, payment_intent, amount_due, created, session_text, period_end, period_start, subscription_id)
        //         VALUES ('$invoice_id', '$customer_id', '$amount_paid', '$currency', '$status', '$invoice_date', '$customer_email', '$payment_intent', '$amount_due', '$created', '$invoice', '$period_end', '$period_start', '$subscription')";

        // Thực hiện truy vấn

    }
    function handleSubscriptionExpired($invoice)
    {

        $customer = $invoice->customer;
        $subscription_id = $invoice->subscription;
        $status = $invoice->status;

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn

        $stmt = $this->connection->prepare("UPDATE subscriptions SET status = ':status' WHERE subscription_id = :subscription_id AND customer_id = :customer_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer,
            ':status' => $status
        ]);
        error_log("Subscription expired for customer: , subscription ID: $subscription_id");
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




        $this->saveInvoiceToDatabase($invoiceId, $customerId, $amountDue, $currency, $status, $customer_email, $subscription_id);



        error_log("Invoice created: ID = $invoiceId, Customer ID = $customerId, Amount Due = $amountDue $currency, Status = $status");
    }
    function handleInvoicePaid($invoice)
    {
        $invoiceId = $invoice->id;
        $customerId = $invoice->customer;
        $amountPaid = $invoice->amount_paid;
        $currency = $invoice->currency;
        $status = $invoice->status;

        $this->updateInvoiceStatusInDatabase($invoiceId, 'paid');


        error_log("Invoice paid: ID = $invoiceId, Customer ID = $customerId, Amount Paid = $amountPaid $currency, Status = $status");
    }

    function handleInvoicePaymentFailed($invoice)
    {
        $invoiceId = $invoice->id;
        $customerId = $invoice->customer;
        $amountDue = $invoice->amount_due;
        $currency = $invoice->currency;
        $status = $invoice->status;

        $this->updateInvoiceStatusInDatabase($invoiceId, 'payment_failed');



        error_log("Invoice payment failed: ID = $invoiceId, Customer ID = $customerId, Amount Due = $amountDue $currency, Status = $status");
    }

    // Hàm để xử lý khi phiên checkout hoàn tất
    function handleCheckoutSessionCompleted($session)
    {
        $customerId = $session->customer;
        $plan = '';
        $email = $session->customer_details->email;
        $subscription = $session->subscription;
        $licenseKey = $this->generateLicenseKey();
        $status =  $session->status;
        $paymentIntentId = $session->payment_intent;


        // Thêm dữ liệu vào bảng subscriptions
        $query = 'INSERT INTO subscriptions (customer_id, plan, status, customer_email, amount, currency, payment_method, license_key, subscription,payment_intent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$customerId, $plan, $status, $email, $session->amount_total / 100, $session->currency, $session->payment_method_collection, $licenseKey, $subscription, $paymentIntentId]);



        error_log('Subscription inserted');
    }

    // Hàm để xử lý khi thanh toán invoice thành công
    function handleInvoicePaymentSucceeded($invoice)
    {
        // Kiểm tra kết nối cơ sở dữ liệu

        // Kiểm tra đầu vào
        if (empty($invoice) || empty($invoice->subscription)) {
            error_log('Invalid invoice data');
            return;
        }


        // Lấy thông tin cần thiết từ invoice
        $customer = $invoice->customer;
        $subscription_id = $invoice->subscription;

        // Lấy thông tin chi tiết của subscription
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;

        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);




        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn

        $stmt = $this->connection->prepare("UPDATE subscriptions SET status = :status, current_period_end = :current_period_end WHERE subscription_id = :subscription_id AND customer_id = :customer_id");
        if (!$stmt) {
            throw new Exception('Query preparation failed');
        }
        $stmt->execute([
            ':status' => $status,
            ':current_period_end' => $current_period_end_date,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer
        ]);
        // error_log("Invoice payment succeeded for customer: $customer, subscription ID: $subscription_id, status: $status, current period end: $current_period_end_date ");

    }

    // Hàm để xử lý khi một subscription mới được tạo
    function handleSubscriptionCreated($subscription)
    {


        // Lấy thông tin cần thiết từ subscription
        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $current_period_start = $subscription->current_period_start;
        $current_period_end = $subscription->current_period_end;
        $customer = $subscription->customer;
        $plan = $subscription->plan->id;
        $licenseKey = $this->generateLicenseKey();
        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_start_date = date('Y-m-d H:i:s', $current_period_start);
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

        $status_key = 'active';


        $stmt = $this->connection->prepare("INSERT INTO subscriptions (customer_id, subscription_id, status, current_period_start, current_period_end, customer, subscription_json, plan, bank_name) VALUES (:customer_id, :subscription_id, :status, :current_period_start, :current_period_end, :customer, :subscription_json, :plan, :bank_name)");
        $stmt->execute([
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


        $stmt = $this->connection->prepare("INSERT INTO licensekey (customer_id, status, subscription_id, license_key, send) VALUES (:customer_id, :status, :subscription_id, :license_key, :send)");
        $stmt->execute([
            ':customer_id' => $customer,
            ':subscription_id' => $subscription_id,
            ':license_key' => $licenseKey,
            ':status' => $status_key,
            ':send' => 'not'

        ]);

        error_log("Subscription created for customer: $customer, subscription ID: $subscription_id, status: $status, current period start: $current_period_start_date, current period end: $current_period_end_date");
    }

    function getCustomerEmailBySubscriptionId($subscription_id)
    {
        try {
            $stmt = $this->connection->prepare("SELECT customer_email FROM invoice WHERE subscription_id = :subscription_id");
            $stmt->execute([':subscription_id' => $subscription_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debugging log to check the result
            error_log("getCustomerEmailBySubscriptionId result: " . json_encode($result));

            return $result ? $result['customer_email'] : null;
        } catch (PDOException $e) {
            error_log("Error occurred in getCustomerEmailBySubscriptionId: " . $e->getMessage());
            return null;
        }
    }







    function saveSubscriptionToDatabase($subscriptionId, $customerId, $status, $currentPeriodEnd)
    {

        // $stmt = $pdo->prepare('INSERT INTO subscriptions (id, customer_id, status, current_period_end) VALUES (?, ?, ?, ?)');


        // $stmt->execute([$subscriptionId, $customerId, $status, date('Y-m-d H:i:s', $currentPeriodEnd)]);


        $stmt = $this->connection->prepare('UPDATE subscriptions SET status = ?, current_period_end = ? WHERE customer_id = ?');
        $stmt->execute([$status, date('Y-m-d H:i:s', $currentPeriodEnd), $$customerId]);
    }

    function handleInvoiceFinalized($invoice)
    {
        // Lấy thông tin từ đối tượng hóa đơn
        $invoice_id = $invoice->id;
        $amount_paid = $invoice->amount_paid;
        $currency = $invoice->currency;
        $status = $invoice->status;
        $customer_email = $invoice->customer_email;
        $payment_intent = $invoice->payment_intent;
        $amount_due = $invoice->amount_due;
        $created = $invoice->created;
        $period_end = $invoice->period_end;
        $period_start = $invoice->period_start;
        $subscription_id = $invoice->subscription;
        $customer_id = $invoice->customer;

        $invoice_date = date('Y-m-d H:i:s', $invoice->created);

        // Kiểm tra xem licenseKey tồn tại trong bảng licensekey


        // Sử dụng Prepared Statement để tránh tấn công SQL injection
        $stmt = $this->connection->prepare("INSERT INTO invoice (invoice_id, amount_paid, currency, status, invoice_datetime, customer_email, payment_intent, amount_due, created, period_end, period_start, subscription_id, customer_id) VALUES (:invoice_id, :amount_paid, :currency, :status, :invoice_datetime, :customer_email, :payment_intent, :amount_due, :created, :period_end, :period_start, :subscription_id, :customer_id)");
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
        $stmt = $this->connection->prepare("UPDATE subscriptions SET customer_email = :customer_email WHERE subscription_id = :subscription_id");
        $stmt->execute([
            ':customer_email' => $customer_email,
            ':subscription_id' => $subscription_id
        ]);
        $stmt = $this->connection->prepare("SELECT license_key FROM licensekey WHERE subscription_id = :subscription_id");
        $stmt->execute([':subscription_id' => $subscription_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Kiểm tra và gửi email chỉ khi licenseKey tồn tại

        if ($result && isset($result['license_key'])) {

            $licenseKey = $result['license_key'];

            // Kiểm tra lại email của khách hàng
            $customer_email = $this->getCustomerEmailBySubscriptionId($subscription_id);

            error_log("EMAIL: $customer_email");
            error_log("licensekey: $licenseKey");




            $mail = new PHPMailer(true);

            //Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'thanbatbai3092002@gmail.com';  // Your Gmail address
            $mail->Password   = 'etejnwheciweprdo';  // Your Gmail password or app-specific password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;  // TCP port to connect to

            //Recipients
            $mail->setFrom('no-reply@example.com', 'Mailer');
            $mail->addAddress($customer_email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your License Key';
            $mail->Body    = "Dear $customer_email,<br><br>Your license key is: $licenseKey<br><br>Thank you for your subscription! subid: " . $subscription_id;

            // Send the email
            $mail->send();
            $licensekey_stmt = $this->connection->prepare("UPDATE licensekey SET send = :send WHERE subscription_id = :subscription_id");
            $licensekey_stmt->execute([
                ':send' => 'ok',
                ':subscription_id' => $subscription_id
            ]);
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }
    }


    function handleSubscriptionUpdated($subscription)
    {

        // Lấy thông tin cần thiết từ subscription
        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;

        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

        $invoiceId = $subscription->latest_invoice;
        // Cập nhật thông tin đăng ký trong cơ sở dữ liệu của bạn

        $stmt = $this->connection->prepare("UPDATE subscriptions SET status = :status, current_period_end = :current_period_end WHERE subscription_id = :subscription_id AND customer_id = :customer_id");
        $stmt->execute([
            ':status' => $status,
            ':current_period_end' => $current_period_end_date,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer
        ]);

        // Kiểm tra giá trị của $status và cập nhật trạng thái của licensekey
        if ($status === 'expired') {
            $status = 'inactive';
        } elseif ($status === 'active') {
            $status = 'active';
        } else {
            // Xử lý trạng thái khác nếu cần
        }

        // Thực hiện câu lệnh SQL UPDATE cho bảng licensekey
        $licensekey_stmt = $this->connection->prepare("UPDATE licensekey SET status = :status WHERE subscription_id = :subscription_id");
        $licensekey_stmt->execute([
            ':status' => $status,
            ':subscription_id' => $subscription_id
        ]);
    }

    function updateSubscriptionStatus($subscriptionId, $status, $currentPeriodEnd)
    {

        $stmt = $this->connection->prepare('UPDATE subscriptions SET status = ?, current_period_end = ? WHERE subscription_id = ?');
        $stmt->execute([$status, date('Y-m-d H:i:s', $currentPeriodEnd), $subscriptionId]);
    }
    // Hàm để xử lý khi một subscription bị xóa
    function handleSubscriptionDeleted($subscription)
    {


        // Lấy thông tin cần thiết từ subscription
        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn

        $stmt = $this->connection->prepare("UPDATE subscriptions SET status = :status WHERE subscription_id = :subscription_id AND customer_id = :customer_id");
        $stmt->execute([
            ':status' => $status,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer,

        ]);
        error_log("Subscription deleted for customer: $customer, subscription ID: $subscription_id");
    }

    // Hàm để tạo license key ngẫu nhiên
    function generateLicenseKey()
    {
        return bin2hex(random_bytes(16));
    }
    function saveInvoiceToDatabase($invoiceId, $customerId, $amountDue, $currency, $status, $customer_email, $subscription_id)
    {


        $stmt = $this->connection->prepare('SELECT * FROM customers WHERE customer_id = ?');
        $stmt->execute([$customerId]);
        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCustomer) {
            // If the customer already exists, update their email
            $stmt = $this->connection->prepare('UPDATE customers SET email = ? WHERE customer_id = ?');
            $stmt->execute([$customer_email, $customerId]);
        } else {
            // If the customer does not exist, insert a new record
            $stmt = $this->connection->prepare('INSERT INTO customers (customer_id, email) VALUES (?, ?)');
            $stmt->execute([$customerId, $customer_email]);
        }
        // Insert into invoice table
        $stmt = $this->connection->prepare('INSERT INTO invoice (invoice_id, customer_id, amount_due, currency, status, customer_email, subscription_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$invoiceId, $customerId, $amountDue, $currency, $status, $customer_email, $subscription_id]);



        // Commit the transaction
        $this->connection->commit();
    }

    function updateInvoiceStatusInDatabase($invoiceId, $status)
    {

        $subscriptionId = $invoiceId->subscription;
        $query = 'UPDATE subscriptions SET status = ? WHERE subscription_id = ?';
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['unpaid', $subscriptionId]);

        $stmt = $this->connection->prepare('UPDATE invoice SET status = ? WHERE invoice_id = ?');
        $stmt->execute([$status, $invoiceId]);
    }

    function getCustomerEmailById($customerId)
    {
        $stmt = $this->connection->prepare('SELECT email FROM customers WHERE customer_id = ?');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);


        return $customer ? $customer['email'] : null;
    }


    function handleCheckoutSessionExpired($session)
    {
        $customerId = $session->customer;


        // Add logic for handling expired checkout session, such as sending email notification to customer
        // sendCheckoutSessionExpiredEmail($customerId);
    }
}
