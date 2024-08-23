<?php
// require './';
require_once '../common.php';
require_once '../vendor/autoload.php';

use Stripe\Stripe;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$stripeSecrets = Common::getStripeSecrets();
if ($stripeSecrets) {
    $apiKey = $stripeSecrets['apiKey'];
    $endpointSecret = $stripeSecrets['endpointSecret'];
    $plans = $stripeSecrets['plans'];
} else {
    die('No active Stripe');
}


Stripe::setApiKey($apiKey);
define('ENDPOINT_SECRET', $endpointSecret);



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


class StripeApiFunction
{
    private $connection;
    private $apiKey;
    private $endpointSecret;
    private $plans;
    // Hàm khởi tạo
    public function __construct()
    {

        $this->init();
    }
    function init()
    {


        $stripeSecrets = Common::getStripeSecrets();

        if ($stripeSecrets) {
            $this->apiKey = $stripeSecrets['apiKey'];
            $this->endpointSecret = $stripeSecrets['endpointSecret'];
            $this->plans = json_decode($stripeSecrets['plans'], true);
        } else {
            throw new Exception('No active Stripe secrets found.');
        }
        $this->connection = Common::getDatabaseConnection();
        // $this->plans = Common::$plans;
        if (!$this->connection) {
            throw new Exception('Database connection could not be established.');
        }
    }


    public $web_domain = 'https://www.vidcombo.com/';

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



    function findSubscriptionIdByLicenseKey($licenseKey)
    {
        // Hiển thị giá trị licenseKey để kiểm tra


        // Sử dụng kết nối PDO hiện tại
        $conn = $this->connection;

        // Chuẩn bị và thực hiện truy vấn
        $stmt = $conn->prepare("SELECT `subscription_id` FROM licensekey WHERE `license_key` = :license_key");
        $stmt->execute([':license_key' => $licenseKey]);

        // Lấy kết quả truy vấn
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        // Trả về subscription_id nếu có, nếu không trả về null
        return $device['subscription_id'] ?? null;
    }
    function createCheckoutSession()
    {
        // Lấy dữ liệu từ yêu cầu
        $body = file_get_contents('php://input');
        parse_str($body, $data);
        $licenseKey = $data['license_key'] ?? null;
        $plan = $data['plan'] ?? null;
        // Lấy giá trị plan từ mảng $plans
        $planKey = $this->plans[$plan] ?? null;

        if (!$plan) {
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
                case 'invoiceitem.created': // Khi thanh toán hóa đơn không thành công
                    $this->handleinvoiceitem($event->data->object);
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
        $stmt = $this->connection->prepare("SELECT `plan_alias` FROM licensekey WHERE customer_id = :customer_id");
        $stmt->execute([':customer_id' => $customer_id]);
        $plan_alias = $stmt->fetchColumn();

        $query = 'INSERT INTO refund (amount_captured, amount_refunded, customer_id, invoice_id, payment_intent, payment_method, receipt_url, plan_alias, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->connection->prepare($query);
        $stmt->execute([$amount_captured, $amount_refunded, $customer_id, $invoice_id, $payment_intent, $payment_method, $receipt_url, $plan_alias, $created_date]);

        $updateQuery = 'UPDATE licensekey SET status = "inactive" WHERE customer_id = ?';
        $updateStmt = $this->connection->prepare($updateQuery);
        $updateStmt->execute([$customer_id]);
        error_log('update licensekey' . $refund);
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
        $pre_end = $invoice->lines['data'][0]['period']['end'];
        $plan_id = $invoice->lines['data'][0]['plan']['id'];
        $period_end = $pre_end;
        $invoiced_date = date('Y-m-d', $invoice->created);
        $customer_name = $invoice->customer_name;

        try {
            // Cập nhật thông tin trong bảng invoice
            $sql = $this->connection->prepare("UPDATE invoice SET status = :status, subscription_id = :subscription_id, customer_id = :customer_id, amount_paid= :amount_paid, period_end= :period_end WHERE invoice_id = :invoice_id");
            $sql->execute([
                ':status' => $status,
                ':subscription_id' => $subscription_id,
                ':customer_id' => $customer_id,
                ':amount_paid' => $amount_paid,
                ':invoice_id' => $invoice_id,
                ':period_end' => $period_end
            ]);
            error_log("status error: " . $invoice);
            // Chỉ cập nhật bảng licensekey nếu trạng thái là 'paid'
            if ($status == 'paid') {


                $stmt_check = $this->connection->prepare("SELECT COUNT(*) FROM licensekey WHERE customer_id = :customer_id AND subscription_id = :subscription_id");
                $stmt_check->execute([
                    ':customer_id' => $customer_id,
                    ':subscription_id' => $subscription_id,
                ]);

                $keyExists = $stmt_check->fetchColumn();

                if (!$keyExists) {
                    // Nếu key chưa tồn tại, tạo key mới
                    $status_key = 'active';
                    $licenseKey = $this->generateLicenseKey();
                    $plan = $plan_id;
                    $current_period_start_date = $invoice_date;
                    $plan_name = array_search($plan, $this->plans);

                    $stmt2 = $this->connection->prepare("INSERT INTO licensekey (customer_id, status, subscription_id, license_key, send, plan, plan_alias, sk_key, sign_key, created_at) VALUES (:customer_id, :status, :subscription_id, :license_key, :send, :plan, :plan_alias, :sk_key, :sign_key, :created_at)");
                    $stmt2->execute([
                        ':customer_id' => $customer_id,
                        ':subscription_id' => $subscription_id,
                        ':license_key' => $licenseKey,
                        ':status' => $status_key,
                        ':send' => 'not',
                        ':plan' => $plan,
                        ':plan_alias' => $plan_name,
                        ':sk_key' => $this->apiKey,
                        ':sign_key' => $this->endpointSecret,
                        ':created_at' => $current_period_start_date
                    ]);
                }
                $amount_in_dollars = $amount_due / 100;
                $amount_due =  number_format($amount_in_dollars, 2);
                // Gửi email thông báo
                // Create an instance of PHPMailer

                Common::sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date);

                // $mail = new PHPMailer(true);
                // try {
                //     // Server settings
                //     $mail->isSMTP();
                //     $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
                //     $mail->SMTPAuth   = true;
                //     $mail->Username   = 'vidcombo.com@gmail.com';  // Your Gmail address
                //     $mail->Password   = 'fyebyrtcnehwravx';  // Your Gmail password or app-specific password
                //     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                //     $mail->Port       = 587;  // TCP port to connect to

                //     // Recipients
                //     $mail->setFrom('vidcombo.com@gmail.com', 'Vidcombo');
                //     $mail->addAddress($customer_email);

                //     // Content
                //     $mail->isHTML(true);
                //     $mail->Subject = 'Payment Successful';
                //     // Define the email body
                //     $email_body = "
                //     <div  style='background: #F6F2FF;'>

                //         <div style='text-align: center;padding-bottom: 10px; padding-top:10px'>
                //             <img src='https://www.vidcombo.com/images/logo_vidcombo.png' alt=''>
                //         </div>

                //         <div style='padding: 0px; margin: 0px; height: 100%;  font-family: Arial; text-align: center!important'>
                //             <div class='container' style='width: 100%; margin-right: auto; margin-left: auto; color: white;'>
                //                 <div class='' style='display:flex; min-height:50vh!important;justify-content: center;'>
                //                     <div class='main'
                //                         style='box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;background: #FFFFFF; padding: 20px 50px 50px 50px; border-radius: 10px; margin: 0px auto; max-width: 640px; max-height: 430px;display: block;font-family: inherit;'>
                //                           <div style='text-align: center;'>

                //                                 <img src='https://www.vidcombo.com/images/check.png' alt=''>


                //                         </div>
                //                         <h2 style='text-align: center;color: #8522FB;font-size: 30px;margin: 30px 0 30px 0;'>Payment Successful</h2>
                //                         <p style='text-align: center;color: #1D1F24;font-size: 20px;margin: 10px 0 10px 0;'>Hello $customer_name,</p>
                //                         <p style='text-align: center;color: #77797C;font-size: 20px;margin: 10px 0 10px 0;'>Thank you for your payment of <span style='color: #0a0a0a;font-weight: 700;'>$amount_due $</span> on
                //                             $invoiced_date.</p>
                //                     <p style='text-align: center;color: #77797C;'>You can view account at <a href='https://www.vidcombo.com/' style='color: #8522FB;font-weight: 700;'>Vidcombo.com</a></p>

                //                     <hr style='margin-top: 50px;'>
                //                     <h4 style='text-align: center;color:#77797C;font-size: 22px;margin: 10px 0 20px 0;'>Thank you!</h4>
                //                     </div>
                //                 </div>
                //             </div>
                //         </div>
                //     </div>
                //     ";

                //     // Assign the email body
                //     $mail->Body = $email_body;

                //     // Send the email
                //     $mail->send();
                //     echo 'Email has been sent successfully';
                // } catch (Exception $e) {
                //     error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                // }
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
        error_log("Invoice created for customer" . $invoice);
    }

    function handleInvoicePaid($invoice)
{
    // Chuyển $invoice thành một mảng
    $invoice_array = [
        'subscription' => $invoice->subscription,
        'lines' => [
            'data' => $invoice->lines['data']
        ],
        'customer_name' => $invoice->customer_name
    ];
    

   //Lất các thông tin liên quán
    $subscription_id = $invoice_array['subscription'];
    $last_line_item = end($invoice_array['lines']['data']);
    $pre_end = $last_line_item['period']['end'];
    $period_end = date('Y-m-d H:i:s', $pre_end);
    // error_log(' $invoice_array' . $period_end);
    
    // Lấy plan và customer_name từ mảng
    $plan = $last_line_item['plan']['id'];
    $customer_name = $invoice_array['customer_name'];

    // Lấy license_key theo gói subscription từ cơ sở dữ liệu
    $stmt = $this->connection->prepare("SELECT `license_key` FROM licensekey WHERE subscription_id = :subscription_id");
    $stmt->execute([':subscription_id' => $subscription_id]);
    $license_key = $stmt->fetchColumn();

    // Tìm plan_name
    $plan_name = array_search($plan, $this->plans);

    // Cập nhật licensekey trong cơ sở dữ liệu
    $stmt = $this->connection->prepare("UPDATE licensekey SET current_period_end = :current_period_end, plan = :plan, plan_alias =:plan_alias WHERE subscription_id = :subscription_id");
    $stmt->execute([
        ':current_period_end' => $period_end,
        ':plan' => $plan,
        ':subscription_id' => $subscription_id,
        ':plan_alias' => $plan_name,
    ]);

    // Cập nhật redis cache
    require_once '../redis.php';
    $redis = new RedisCache($license_key);
    $redis->setCache('', 3600); // Xóa cache sau 1 giờ

    // Kiểm tra license_key và tình trạng gửi email
    $stmt = $this->connection->prepare("SELECT license_key, send FROM licensekey WHERE subscription_id = :subscription_id");
    $stmt->execute([':subscription_id' => $subscription_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Gửi email nếu license_key tồn tại và chưa được gửi
    if ($result && isset($result['license_key']) && $result['send'] === 'not') {
        $licenseKey = $result['license_key'];

        // Lấy email khách hàng
        $customer_email = $this->getCustomerEmailBySubscriptionId($subscription_id);

        // Gửi licenseKey qua email
        $resu = Common::sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey);
        if ($resu) {
            $licensekey_stmt = $this->connection->prepare("UPDATE licensekey SET send = :send WHERE subscription_id = :subscription_id");
            $licensekey_stmt->execute([
                ':send' => 'ok',
                ':subscription_id' => $subscription_id
            ]);
        }
    } else {
        error_log("No license key found for subscription ID: $subscription_id");
    }
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
        $query = 'INSERT INTO subscriptions (customer_id, plan, status, customer_email, amount, currency, payment_method, license_key, subscription, payment_intent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
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

        $plan_id = $invoice->lines['data'][0]['plan']['id'];
        $current_period_start = $invoice->period_start;



        // error_log("Invoice payment succeeded for customer: $customer, subscription ID: $subscription_id, status: $status, current period end: $current_period_end_date ");

    }

    // Hàm để xử lý khi một subscription mới được tạo
    function handleSubscriptionCreated($subscription)
    {


        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $current_period_start = $subscription->current_period_start;
        $current_period_end = $subscription->current_period_end;
        $customer = $subscription->customer;
        $plan = $subscription->plan->id;
        $licenseKey = $this->generateLicenseKey();
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
        $customer_name = $invoice->customer_name;

        $invoice_date = date('Y-m-d H:i:s', $invoice->created);
        $invoiced_date = date('Y-m-d', $invoice->created);

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




        $stmt = $this->connection->prepare("SELECT license_key, send FROM licensekey WHERE subscription_id = :subscription_id");
        $stmt->execute([':subscription_id' => $subscription_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Kiểm tra và gửi email chỉ khi licenseKey tồn tại

        if ($result && isset($result['license_key']) && $result['send'] === 'not') {

            $licenseKey = $result['license_key'];

            // Kiểm tra lại email của khách hàng
            $customer_email = $this->getCustomerEmailBySubscriptionId($subscription_id);

            error_log("EMAIL: $customer_email");
            error_log("licensekey: $licenseKey");
            
            //Gửi licenseKey qua email
            Common::sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey);

            $licensekey_stmt = $this->connection->prepare("UPDATE licensekey SET send = :send WHERE subscription_id = :subscription_id");
            $licensekey_stmt->execute([
                ':send' => 'ok',
                ':subscription_id' => $subscription_id
            ]);
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }

   // Giá trị từ Stripe
   $amount_in_dollars = $amount_due / 100;
   $amount_due =  number_format($amount_in_dollars, 2);

        if ($status == 'paid') {
            Common::sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date);
        }
    }



    function handleSubscriptionUpdated($subscription)
    {

        // Lấy thông tin cần thiết từ subscription
        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;
        $current_period_start = $subscription->current_period_start;

        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

        // Cập nhật thông tin đăng ký trong cơ sở dữ liệu của bạn


        $stmt = $this->connection->prepare("UPDATE subscriptions SET status = :status, current_period_end = :current_period_end WHERE subscription_id = :subscription_id AND customer_id = :customer_id");
        $stmt->execute([
            ':status' => $status,
            ':current_period_end' => $current_period_end_date,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer
        ]);


        // Kiểm tra giá trị của $status và cập nhật trạng thái của licensekey
        // if ($status === 'expired') {
        //     $status = 'inactive';
        // } elseif ($status === 'active') {
        //     $status = 'active';
        // } else {
        //     // Xử lý trạng thái khác nếu cần
        // }
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
        $period_end_subscription = date('Y-m-d H:i:s', $subscription->current_period_end);

        // Ghi log

        error_log("subscription: " . $subscription);

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn
        $stmt = $this->connection->prepare("UPDATE subscriptions SET status = :status, current_period_end = :current_period_end WHERE subscription_id = :subscription_id AND customer_id = :customer_id");
        $stmt->execute([
            ':status' => $status,
            ':subscription_id' => $subscription_id,
            ':customer_id' => $customer,
            ':current_period_end' => $period_end_subscription
        ]);

        // Lấy period_end từ licensekey
        $licensekey_stmt = $this->connection->prepare("SELECT current_period_end FROM licensekey WHERE subscription_id = :subscription_id");
        $licensekey_stmt->execute([':subscription_id' => $subscription_id]);
        $licensekey = $licensekey_stmt->fetch(PDO::FETCH_ASSOC);
        $period_end_licensekey = $licensekey['current_period_end'];

        // So sánh period_end và cập nhật trạng thái nếu cần
        if ($period_end_subscription <= $period_end_licensekey) {
            $update_stmt = $this->connection->prepare("UPDATE licensekey SET status = :status WHERE subscription_id = :subscription_id");
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
    }
}
