<?php
// require './';
require_once '../common.php';
require_once '../vendor/autoload.php';

$paypalSecret = Common::getPaypalSecrets();


$client_id = $paypalSecret['client_id'];
$clientSecret = $paypalSecret['client_secret'];
$webhookId = $paypalSecret['webhook_id'];
// Kiểm tra URL và gọi hàm xử lý tương ứng
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$apiContext = new \PayPal\Rest\ApiContext(
    new \PayPal\Auth\OAuthTokenCredential(
        $paypalSecret['client_id'],     // Replace with your PayPal Client ID
        $paypalSecret['client_secret']  // Replace with your PayPal Client Secret
    )
);
$apiContext->setConfig(['mode' => 'sandbox']);



$func = isset($_GET['func']) ? trim($_GET['func']) : '';

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
$access_token = get_paypal_access_token($client_id, $clientSecret);

$paypal_funtion = new PaypalApiFunction();
switch ($func) {

    case 'create-checkout-session':
        $paypal_funtion->createSubscription($access_token);
        break;
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
    case 'webhook':
        $paypal_funtion->handlePaypalWebhook();
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo '404 Not Found';
        exit;
}





class PaypalApiFunction
{
    private $connection;
    private $client_id;
    private $clientSecret;
    private $access_token;
    private $plans;
    public $web_domain = 'http://localhost:8080/';
    function __construct()
    {

        $this->init();
    }
    function init()
    {

        $paypalSecret = Common::getPaypalSecrets();
        if ($paypalSecret) {
            $this->client_id = $paypalSecret['client_id'];
            $this->clientSecret = $paypalSecret['client_secret'];
            $this->plans = json_decode($paypalSecret['plans'], true);
        } else {
            throw new Exception('No active Stripe secrets found.');
        }
        $this->connection = Common::getDatabaseConnection();
        if (!$this->connection) {
            throw new Exception('Database connection could not be established.');
        }
        $this->access_token = $this->get_paypal_access_token($this->client_id, $this->clientSecret);
    }
    private function get_paypal_access_token($client_id, $clientSecret)
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
    // Hàm xử lý webhook từ PayPal

    function handlePaypalWebhook()
    {

        // Get the raw POST data from PayPal's webhook

        // Kiểm tra nếu phương thức là POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Lấy nội dung của payload từ webhook
            $payload = file_get_contents('php://input');

            // Chuyển đổi payload từ JSON sang mảng PHP để dễ xử lý
            $data = json_decode($payload, true);

            $event = $data['event_type'];

            try {
                switch ($event) {
                    case 'PAYMENT.SALE.COMPLETED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handlePaymentCompleted($data, $this->access_token);
                        break;
                    case 'PAYMENT.SALE.PENDING': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handlePaymentPending($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.CREATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionCreated($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.UPDATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionUpdate($data, $this->access_token);
                        break;
                    case 'BILLING.SUBSCRIPTION.RE-ACTIVATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionReActivated($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.ACTIVATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionActivated($data, $this->access_token);
                        break;
                    case 'BILLING.SUBSCRIPTION.CANCELLED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionCancelled($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.RENEWED': //khi gois sub hết hạn và đến ngày tự động ra hạn
                        $this->handleSubscriptionRenewed($data);
                        break;
                    default:
                        error_log('Unhandled event type ' . $event);
                }
            } catch (\Exception $e) {
                echo $e->getMessage();
            }


            // Kiểm tra nếu có dữ liệu nhận được
            if (!empty($data)) {
                // Ghi tất cả các sự kiện vào log
                $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
                file_put_contents('log/webhook_debug.log', $logContent, FILE_APPEND);
            } else {
                // Nếu không có dữ liệu trong payload
                file_put_contents('log/webhook_debug.log', "Payload is empty.\n\n", FILE_APPEND);
            }
        } else {
            // Nếu không phải là yêu cầu POST
            file_put_contents('log/webhook_debug.log', "Request method is not POST.\n\n", FILE_APPEND);
        }
    }

    function handleSubscriptionActivated($data, $accessToken)
    {
        // Ghi log sự kiện nhận webhook
        $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handleSubscriptionActivated.log', $logContent, FILE_APPEND);

        // Kiểm tra trạng thái và chuẩn bị thông tin từ webhook
        $status = $data['resource']['status'] == 'ACTIVE' ? 'active' : $data['resource']['status'];
        $subscription_id = $data['resource']['id'];
        $customer_email = $data['resource']['subscriber']['email_address'];
        $customer_name = $data['resource']['subscriber']['email_address'];

        // Gọi API PayPal để lấy chi tiết gói đăng ký
        $plan_id = $data['resource']['plan_id'];
        $planDetails = $this->getPlanDetailsFromPayPal($plan_id, $accessToken);
        if (!$planDetails) {
            error_log("Failed to fetch plan details for plan_id: $plan_id");
            return;
        }

        // Tính toán ngày hết hạn của gói đăng ký
        $current_period_end = $this->calculatePeriodEnd($planDetails['billing_cycles'][0]);

        // Ghi log thời gian hết hạn
        $formatted_period_end = $current_period_end->format('Y-m-d H:i:s');
        file_put_contents('log/handleSubscriptionActivated.log', "current_period_end: $formatted_period_end\n\n", FILE_APPEND);

        // Cập nhật subscription trong cơ sở dữ liệu
        $this->updateSubscription($subscription_id, $status, $formatted_period_end, $customer_email);
        $this->updateInvoiceEmail($subscription_id, $customer_email);

        // Kiểm tra và gửi license key nếu chưa gửi
        $this->checkAndSendLicenseKey($subscription_id, $customer_email, $customer_name, $formatted_period_end,);

        error_log('Subscription activated successfully');
    }

    // Lấy chi tiết plan từ PayPal API
    private function getPlanDetailsFromPayPal($plan_id, $accessToken)
    {
        $url = "https://api.sandbox.paypal.com/v1/billing/plans/{$plan_id}";
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($response === false) {
            error_log("Error fetching plan details: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    // Tính toán ngày hết hạn từ gói đăng ký
    private function calculatePeriodEnd($billingCycle)
    {
        $current_period_end = new DateTime(); // Giả sử ngày bắt đầu là hiện tại
        $frequency = $billingCycle['pricing_scheme'];
        $interval = $billingCycle['frequency'];

        if (isset($interval['interval_unit']) && $interval['interval_unit'] == 'MONTH') {
            $current_period_end->modify('+' . $interval['interval_count'] . ' month');
        } elseif (isset($interval['interval_unit']) && $interval['interval_unit'] == 'DAY') {
            $current_period_end->modify('+' . $interval['interval_count'] . ' day');
        }

        return $current_period_end;
    }

    // Cập nhật thông tin subscription vào cơ sở dữ liệu
    private function updateSubscription($subscription_id, $status, $current_period_end, $customer_email)
    {
        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` = :status, `current_period_end` = :current_period_end, `customer_email` = :customer_email WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':status' => $status,
            ':current_period_end' => $current_period_end,
            ':customer_email' => $customer_email
        ]);
    }

    // Cập nhật email của khách hàng trong hóa đơn
    private function updateInvoiceEmail($subscription_id, $customer_email)
    {
        $stmt = $this->connection->prepare("UPDATE `invoice` SET `customer_email` = :customer_email WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':customer_email' => $customer_email
        ]);
    }

    // Kiểm tra và gửi license key nếu chưa gửi
    private function checkAndSendLicenseKey($subscription_id, $customer_email, $customer_name, $formatted_period_end)
    {
        $stmt = $this->connection->prepare("SELECT `license_key`, `send` FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        $stmt->execute([':subscription_id' => $subscription_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['license_key']) && $result['send'] === 'not') {
            $licenseKey = $result['license_key'];
            error_log("Sending license key to: $customer_email");

            // Gửi license key qua email
            $send_status = Common::sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey);

            if ($send_status) {
                $this->updateLicenseKeySendStatus($subscription_id, $formatted_period_end);
            }
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }
    }

    // Cập nhật trạng thái gửi license key
    private function updateLicenseKeySendStatus($subscription_id, $formatted_period_end)
    {
        $stmt = $this->connection->prepare("UPDATE `licensekey` SET `send` = :send , `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':send' => 'ok',
            ':subscription_id' => $subscription_id,
            ':current_period_end' => $formatted_period_end
        ]);
    }







    //--------- Start funtion webhook --------------/
    function handlePaymentCompleted($data, $access_token)
    {
        $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handlePaymentCompleted.log', $logContent, FILE_APPEND);
        $subscription_id = $data['resource']['billing_agreement_id'];
        $create_time = $data['create_time'];

        $stmt = $this->connection->prepare("SELECT * FROM subscriptions WHERE subscription_id = :subscription_id");
        $stmt->execute(['subscription_id' => $subscription_id]);
        $subscription = $stmt->fetch();

        $customer_email = $subscription['customer_email'];
        $customer_name = $subscription['customer_email'];
        $current_period_end = $subscription['current_period_end'];

        $plan = $subscription['plan'];
        $plan_alias = array_search($plan, $this->plans);
        $sk_key = $this->client_id;
        $sign_key = $this->clientSecret;
        $date = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $create_time);
        $formattedDateCreate_time = $date->format('Y-m-d H:i:s');
        $licenseKey = $this->generateLicenseKey();
        $stmtCheck = $this->connection->prepare("SELECT COUNT(*) FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        $stmtCheck->execute([':subscription_id' => $subscription_id]);
        $exists = $stmtCheck->fetchColumn();
        if ($exists == 0) {
            $stmt2 = $this->connection->prepare("INSERT INTO `licensekey` (`status`, `subscription_id`, `license_key`, `send`, `plan`, `plan_alias`, `sk_key`, `sign_key`, `created_at`, `current_period_end`) VALUES ( :status, :subscription_id, :license_key, :send, :plan, :plan_alias, :sk_key, :sign_key, :created_at, :current_period_end)");
            $stmt2->execute([
                ':subscription_id' => $subscription_id,
                ':license_key' => $licenseKey,
                ':status' => 'active',
                ':send' => 'not',
                ':plan' => $plan,
                ':plan_alias' => $plan_alias,
                ':sk_key' => $sk_key,
                ':sign_key' => $sign_key,
                ':created_at' => $formattedDateCreate_time,
                ':current_period_end' => $current_period_end,
            ]);
        } else {
            // Gia hạn
            $current_period_end_date = new DateTime($current_period_end); // Ngày kết thúc hiện tại


            error_log($plan);
            $url = "https://api.sandbox.paypal.com/v1/billing/plans/{$plan}";
            $headers = [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch);
                error_log("Error fetching plan details: $error");
                curl_close($ch);
                return;
            }

            curl_close($ch);
            $planDetails = json_decode($response, true);
            $logContent = "planDetails:\n"
                . "Invoice ID: " . print_r($planDetails, true) . "\n";

            file_put_contents('log/handlePaymentCompleted.log', $logContent, FILE_APPEND);
            $frequency = $planDetails['billing_cycles'][0]['frequency'];

            if (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'MONTH') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' month');
            } elseif (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'DAY') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' day');
            }


            // Cập nhật lại ngày hết hạn
            $new_period_end = $current_period_end_date->format('Y-m-d H:i:s');
            $stmtUpdate = $this->connection->prepare("UPDATE `subscriptions` SET `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
            $stmtUpdate->execute([
                ':current_period_end' => $new_period_end,
                ':subscription_id' => $subscription_id,
            ]);
            $stmt = $this->connection->prepare("UPDATE `licensekey` SET `current_period_end` = :current_period_end  WHERE `subscription_id` = :subscription_id");
            $stmt->execute([
                ':current_period_end' => $new_period_end,
                ':subscription_id' => $subscription_id,

            ]);
        }



        $stmt3 = $this->connection->prepare("INSERT INTO `invoice` (`invoice_id`,`customer_email`, `status`, `payment_intent`, `period_end`, `period_start`, `subscription_id`, `currency`, `amount_due`, `created`, `amount_paid`, `invoice_datetime`)
                    VALUES (:invoice_id, :customer_email, :status, :payment_intent, :period_end, :period_start, :subscription_id, :currency, :amount_due, :created, :amount_paid, :invoice_datetime)");

        $stmt3->execute([
            ':invoice_id' => $data['id'],
            ':status' => 'paid',
            ':customer_email' => $customer_email,
            ':payment_intent' => $data['resource']['id'],
            ':period_end' => strtotime($current_period_end),
            ':period_start' => strtotime($data['create_time']),
            ':subscription_id' => $subscription_id,
            ':currency' => $data['resource']['amount']['currency'],
            ':amount_due' => $data['resource']['amount']['total'],
            ':created' => strtotime($data['create_time']),
            ':amount_paid' => $data['resource']['amount']['details']['subtotal'],
            ':invoice_datetime' => $formattedDateCreate_time,
        ]);
        $logContent = "Invoice insertion complete:\n"
            . "Invoice ID: " . print_r($data['id'], true) . "\n"
            . "Customer Email: " . print_r($customer_email, true) . "\n"
            . "Payment Intent: " . print_r($data['resource']['id'], true) . "\n"
            . "Currency: " . print_r($data['resource']['amount']['currency'], true) . "\n"
            . "Total Amount: " . print_r($data['resource']['amount']['total'], true) . "\n"
            . "Subtotal: " . print_r($data['resource']['amount']['details']['subtotal'], true) . "\n"
            . "Invoice Date Time: " . print_r($formattedDateCreate_time, true) . "\n\n";

        file_put_contents('log/handlePaymentCompleted.log', $logContent, FILE_APPEND);
        $stmt = $this->connection->prepare("SELECT `license_key`, `send` FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        $stmt->execute([':subscription_id' => $subscription_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['license_key']) && $result['send'] === 'not') {
            $licenseKey = $result['license_key'];
            error_log("Sending license key to: $customer_email");

            // Gửi license key qua email
            $send_status = Common::sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey);

            if ($send_status) {
                $stmt = $this->connection->prepare("UPDATE `licensekey` SET `send` = :send  WHERE `subscription_id` = :subscription_id");
                $stmt->execute([
                    ':send' => 'ok',
                    ':subscription_id' => $subscription_id,

                ]);
            }
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }
        error_log('paymentCOmple');
    }
    function handleSubscriptionUpdate($data, $access_token)
    {
        $logContent = "Webhook event received:\n" . print_r( $data, true) . "\n\n";
        file_put_contents('log/handleSubscriptionUpdate.log', $logContent, FILE_APPEND);

        $subscription_id = $data['resource']['id'];
        $plan = $data['resource']['plan_id'];
        error_log('end' . $subscription_id);
        $plan_alias = array_search($plan, $this->plans);

        $stmtUpdate = $this->connection->prepare("UPDATE `subscriptions` SET `plan` = :plan WHERE `subscription_id` = :subscription_id");
        $stmtUpdate->execute([
            ':subscription_id' => $subscription_id,
            ':plan' => $plan,
        ]);
        $stmt = $this->connection->prepare("UPDATE `licensekey` SET `plan` = :plan, `plan_alias` = :plan_alias  WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':plan' => $plan,
            ':plan_alias' => $plan_alias,
         

        ]);

       
    }
    function handlePaymentPending($data)
    {
        $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handlePaymentPending.log', $logContent, FILE_APPEND);
    }

    function handleSubscriptionRenewed($data): void
    {
        $subscription_id = $data['resource']['id']; // ID của subscription
        $nextBillingTime = $data['resource']['billing_info']['next_billing_time']; // Ngày gia hạn tiếp theo
        $dateTime = new DateTime($nextBillingTime);
        $current_period_end = $dateTime->format('Y-m-d H:i:s');
        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':current_period_end' => $current_period_end,
        ]);
        $stmt = $this->connection->prepare("UPDATE `licensekey` SET `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':current_period_end' => $current_period_end,
        ]);
    }
    function handleSubscriptionCreated($data)
    {
        $subscription = $data['resource'];
        $logContent = "Webhook event received:\n" . print_r($subscription, true) . "\n\n";
        file_put_contents('log/handleSubscriptionCreated.log', $logContent, FILE_APPEND);


        $subscription_id = $data['resource']['id'];
        $logContent = "Subscription_id:\n" . print_r($subscription_id, true) . "\n\n";
        file_put_contents('log/handleSubscriptionCreated.log', $logContent, FILE_APPEND);
        $status = $data['resource']['status'];
        $current_period_start_iso = $data['resource']['start_time'];
        $dateTime = new DateTime($current_period_start_iso);
        $current_period_start = $dateTime->format('Y-m-d H:i:s');
        $create_time = $data['create_time'];
        $plan = $data['resource']['plan_id'];
        $subcrscription_json = json_encode($data);



        $stmt = $this->connection->prepare("INSERT INTO `subscriptions` (`subscription_id`, `status`, `current_period_start`, `create_time`, `plan`, `subscription_json`, `bank_name`) VALUES (:subscription_id, :status, :current_period_start, :create_time, :plan, :subscription_json,  :bank_name)");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':status' => $status,
            ':current_period_start' => $current_period_start,
            ':create_time' => $create_time,
            ':plan' => $plan,
            ':subscription_json' => $subcrscription_json,
            ':bank_name' => 'PayPal'
        ]);
    }


    function handleSubscriptionReActivated($data)
    {
        $logContent = "handleSubscriptionReActivated:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handleSubscriptionReActivated.log', $logContent, FILE_APPEND);
        $subscription_id = $data['resource']['id'];
        $current_period_end_iso = $data['resource']['agreement_details']['next_billing_date'];
        if ($data['resource']['state'] == 'Active') {
            $status = 'active';
        }
        $dateTime = new DateTime($current_period_end_iso);
        $current_period_end = $dateTime->format('Y-m-d H:i:s');
        $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` =:status, `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':status' => $status,
            ':subscription_id' => $subscription_id,
            ':current_period_end' => $current_period_end,
        ]);
        $stmt = $this->connection->prepare("UPDATE `licensekey` SET `current_period_end` = :current_period_end WHERE `subscription_id` = :subscription_id");
        $stmt->execute([
            ':subscription_id' => $subscription_id,
            ':current_period_end' => $current_period_end,
        ]);
    }
    function handleSubscriptionCancelled($data)
    {
        $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handleSubscriptionCancelled.log', $logContent, FILE_APPEND);
        $subscription_id = $data['resource']['id'];
        $status = $data['resource']['status'];
        if ($status === 'CANCELLED') {
            $status = 'canceled';
            $stmt = $this->connection->prepare("UPDATE `subscriptions` SET `status` = :status WHERE `subscription_id` = :subscription_id");
            $stmt->execute([
                ':subscription_id' => $subscription_id,
                ':status' => $status,
            ]);
        }
    }



    // ----- End webhooks funtion  ------- //



    function upSubscription($accessToken)
    {
        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $subscription_id = $data['subscription_id'];
        $planId = $data['planId'];
        $url = "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscription_id}";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];
        $data = [
            "plan_id" => $planId,  // The new plan ID you want to update to
        ];

        // Step 4: Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute the request
        $response = curl_exec($ch);
        curl_close($ch);

        // Handle the response
        $result = json_decode($response, true);

        var_dump($result);
        // Check for success or failure
        // if (isset($result['id'])) {
        //     // Successfully updated subscription
        //     return $result;
        // } else {
        //     // Handle error
        //     return "Error: " . $result['message'] ?? 'Unknown error';
        // }
    }

    function listProducts($accessToken)
    {
        $url = "https://api-m.sandbox.paypal.com/v1/catalogs/products";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo "cURL Error: $error";
            curl_close($ch);
            return;
        }

        curl_close($ch);

        $products = json_decode($response, true);


        if (isset($products['products']) && count($products['products']) > 0) {
            echo json_encode($products['products'], JSON_PRETTY_PRINT);
        } else {
            echo json_encode(["message" => "No products found."]);
        }
    }


    function createProduct($accessToken)
    {
        $url = "https://api-m.sandbox.paypal.com/v1/catalogs/products";
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $data = [
            "name" => "Gói đăng ký Premium Của khánh", // Tên sản phẩm
            "description" => "Gói đăng ký dịch vụ hàng tháng", // Mô tả sản phẩm
            "type" => "SERVICE", // Loại sản phẩm, có thể là SERVICE hoặc PHYSICAL
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    function deleteProducts($accessToken)
    {

        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $product_id = $data['product_id'];

        // URL to delete the product
        $deleteUrl = "https://api-m.sandbox.paypal.com/v1/catalogs/products/{$product_id}";
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        // Perform the DELETE request
        $chDelete = curl_init($deleteUrl);
        curl_setopt($chDelete, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($chDelete, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chDelete, CURLOPT_CUSTOMREQUEST, "DELETE");

        $deleteResponse = curl_exec($chDelete);

        var_dump($deleteResponse);
        if ($deleteResponse === false) {
            $error = curl_error($chDelete);
            echo "cURL Error while deleting product {$product_id}: $error<br>";
        } else {
            echo "Successfully deleted product with ID: {$product_id}<br>";
        }

        curl_close($chDelete);
    }


    function createPlans($accessToken)
    {
        $url = "https://api-m.sandbox.paypal.com/v1/billing/plans";
        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $product_id = $data['product_id'];
        $plan_name = $data['plan_name'];
        $number_month = $data['number_month'];
        $value_price = $data['value_price'];

        // Plan details - customize this as needed
        $planData = [
            "product_id" => $product_id,  // The product ID that you obtained from listProducts
            "name" => $plan_name,  // Plan name
            "description" => "A monthly subscription to our service.",  // Plan description
            "billing_cycles" => [
                [
                    "frequency" => [
                        "interval_unit" => "DAY",  // Frequency unit: MONTH, DAY, etc.
                        "interval_count" => $number_month,  // How often the plan is billed
                    ],
                    "tenure_type" => "REGULAR",
                    "sequence" => 1,
                    "total_cycles" => 0,  // 0 means no end to the cycle (indefinite)
                    "pricing_scheme" => [
                        "fixed_price" => [
                            "value" => $value_price,  // Price for the plan
                            "currency_code" => "USD"  // Currency code
                        ]
                    ]
                ]
            ],
            "payment_preferences" => [
                "auto_bill_outstanding" => true,  // Auto-bill for outstanding invoices
                "payment_failure_threshold" => 3  // Number of failed payments before cancellation
            ],
            "taxes" => [
                "percentage" => "10",  // Tax percentage
                "inclusive" => false  // Whether tax is inclusive or exclusive
            ]
        ];

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($planData));

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo "cURL Error: $error";
            curl_close($ch);
            return;
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        if (isset($responseData['id'])) {
            echo "Plan Created Successfully. Plan ID: " . $responseData['id'];
        } else {
            echo "Failed to create plan.";
        }
    }

    function listPlans($accessToken)
    {
        $url = "https://api-m.sandbox.paypal.com/v1/billing/plans";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo json_encode(["error" => "cURL Error: $error"]);
            curl_close($ch);
            return;
        }

        curl_close($ch);

        $plans = json_decode($response, true);
        if (isset($plans['plans']) && count($plans['plans']) > 0) {
            // Return the list of plans as JSON
            echo json_encode($plans['plans'], JSON_PRETTY_PRINT);
        } else {
            // Return a JSON message when no plans are found
            echo json_encode(["message" => "No plans found."]);
        }
    }


    function getPlanDetails($accessToken)
    {
        $body = file_get_contents('php://input');
        parse_str($body, $data);
        $planId = $data['planId'];
        $url = "https://api-m.sandbox.paypal.com/v1/billing/plans/{$planId}";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo json_encode(["error" => "cURL Error: $error"]);
            curl_close($ch);
            return;
        }

        curl_close($ch);

        $planDetails = json_decode($response, true);

        if (isset($planDetails['id'])) {
            $responseData = [
                "Plan ID" => $planDetails['id'],
                "Name" => $planDetails['name'],
                "Description" => $planDetails['description'],
                "Status" => $planDetails['status'],
            ];

            // Billing cycle details
            if (isset($planDetails['billing_cycles']) && count($planDetails['billing_cycles']) > 0) {
                $billingCycles = [];
                foreach ($planDetails['billing_cycles'] as $cycle) {
                    $billingCycles[] = [
                        "Billing Cycle Frequency" => $cycle['frequency']['interval_count'] . " " . $cycle['frequency']['interval_unit'],
                        "Total Cycles" => $cycle['total_cycles'],
                        "Pricing" => $cycle['pricing_scheme']['fixed_price']['value'] . " " . $cycle['pricing_scheme']['fixed_price']['currency_code']
                    ];
                }
                $responseData['Billing Cycles'] = $billingCycles;
            }

            // Payment Preferences
            if (isset($planDetails['payment_preferences'])) {
                $responseData['Payment Preferences'] = [
                    "Auto Bill Outstanding" => $planDetails['payment_preferences']['auto_bill_outstanding'] ? 'Yes' : 'No',
                    "Payment Failure Threshold" => $planDetails['payment_preferences']['payment_failure_threshold']
                ];
            }

            // Taxes
            if (isset($planDetails['taxes'])) {
                $responseData['Taxes'] = [
                    "Tax Percentage" => $planDetails['taxes']['percentage'] . "%",
                    "Tax Inclusive" => $planDetails['taxes']['inclusive'] ? 'Yes' : 'No'
                ];
            }

            // Return the plan details as JSON
            echo json_encode($responseData, JSON_PRETTY_PRINT);
        } else {
            echo json_encode(["message" => "Plan not found or error retrieving plan details."]);
        }
    }


    function deleteAllPlans($accessToken)
    {
        // Step 1: List all plans
        $url = "https://api-m.sandbox.paypal.com/v1/billing/plans";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        // Initialize cURL for the list request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo "cURL Error while fetching plans: $error";
            curl_close($ch);
            return;
        }

        curl_close($ch);

        // Decode the response to get the plan list
        $plans = json_decode($response, true);

        if (isset($plans['plans']) && count($plans['plans']) > 0) {
            // Step 2: Loop through each plan and delete it
            foreach ($plans['plans'] as $plan) {
                $planId = $plan['id'];

                // URL to delete the plan
                $deleteUrl = "https://api-m.sandbox.paypal.com/v1/billing/plans/{$planId}";

                // Perform the DELETE request
                $chDelete = curl_init($deleteUrl);
                curl_setopt($chDelete, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($chDelete, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chDelete, CURLOPT_CUSTOMREQUEST, "DELETE");

                $deleteResponse = curl_exec($chDelete);

                if ($deleteResponse === false) {
                    $error = curl_error($chDelete);
                    echo "cURL Error while deleting plan {$planId}: $error<br>";
                } else {
                    echo "Successfully deleted plan with ID: {$planId}<br>";
                }

                curl_close($chDelete);
            }
        } else {
            echo "No plans found to delete.";
        }
    }
    function getSubscriptionStatus($accessToken)
    {
        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $subscriptionId = $data['subscriptionId'];
        $url = "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/$subscriptionId"; // Replace with live API endpoint for production

        // Set up the headers
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        // Initialize cURL session
        $ch = curl_init($url);

        // Set the cURL options
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Get response as string
        curl_setopt($ch, CURLOPT_HTTPGET, true);  // Use GET method to retrieve subscription info

        // Execute the cURL session and capture the response
        $response = curl_exec($ch);

        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            echo json_encode(["error" => "cURL Error", "message" => $error]);
            curl_close($ch);
            return;
        }

        // Close the cURL session
        curl_close($ch);

        // Decode the JSON response
        $subscriptionData = json_decode($response, true);
        if (isset($subscriptionData['id'])) {
            echo json_encode([
                'subscription_id' => $subscriptionData['id'],
                'status' => $subscriptionData['status'],
                'status_update_time' => $subscriptionData['status_update_time'] ?? 'N/A',
                'plan_id' => $subscriptionData['plan_id'],
                'subscriber_email' => $subscriptionData['subscriber']['email_address'] ?? 'N/A',
                'create_time' => $subscriptionData['create_time'],
                'links' => $subscriptionData['links'] ?? [], // Links for further actions like cancel or view
                'billing_info' => $subscriptionData['billing_info'] ?? [], // Billing details
                'shipping_address' => $subscriptionData['shipping_address'] ?? [], // If shipping is provided
                'agreement_details' => $subscriptionData['agreement_details'] ?? [], // Agreement details
                'plan' => $subscriptionData['plan'] ?? [], // Plan details
            ]);
        } else {
            echo json_encode([
                'error' => 'Error retrieving subscription details',
                'message' => $subscriptionData['message'] ?? 'Unknown error'
            ]);
        }
    }


    function createSubscription($accessToken)
    {
        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $plan = isset($data['plan']) ? $data['plan'] : 'plan1';
        $planKey = isset($this->plans[$plan]) ? $this->plans[$plan] : '';
        $subscriptionData = [
            'plan_id' => $planKey, // Plan ID from PayPal
            'auto_renewal' => true, // Auto renewal for the subscription
            'application_context' => [
                'brand_name' => 'RIVERNET',
                'locale' => 'en-US',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS', // Or 'NO_SHIPPING' if you don’t need shipping info
                'user_action' => 'SUBSCRIBE_NOW', // You can set this to 'CONTINUE' for subscription renewal
                'return_url' => $this->web_domain . 'paypal/success', // Redirect URL after payment success
                'cancel_url' => $this->web_domain, // Redirect URL if payment is cancelled
            ]
        ];

        $url = "https://api-m.sandbox.paypal.com/v1/billing/subscriptions";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subscriptionData));

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo json_encode(["error" => "cURL Error while creating subscription", "message" => $error]);
            curl_close($ch);
            return;
        }

        curl_close($ch);

        $subscriptionResponse = json_decode($response, true);
        if (isset($subscriptionResponse['id'])) {

            echo json_encode([
                'subscription_id' => $subscriptionResponse['id'],
                'status' => $subscriptionResponse['status'],
                'approval_url' => $subscriptionResponse['links'][0]['href'] ?? null
            ]);
        } else {
            echo json_encode([
                'error' => 'Error creating subscription',
                'message' => $subscriptionResponse['message'] ?? 'Unknown error'
            ]);
        }
    }


    function cancelSubscription($accessToken)
    {
        // Get the data from the input
        $body = file_get_contents('php://input');
        parse_str($body, $data);
        $subscriptionId = $data['subscriptionId'] ?? null;

        if (!$subscriptionId) {
            echo json_encode(["error" => "Subscription ID is required"]);
            return;
        }

        // Set the cancellation URL with the correct subscription ID
        $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions/{$subscriptionId}/cancel";

        // Headers for the API request
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        // Data for the cancellation request
        $data = [
            'reason' => 'User requested cancellation' // Reason for cancellation
        ];

        // Initialize cURL session
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); // POST method
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute cURL session and capture the response
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code

        if ($response === false) {
            $error = curl_error($ch);
            echo json_encode(["error" => "cURL Error while canceling subscription", "message" => $error]);
            curl_close($ch);
            return;
        }

        curl_close($ch);

        // Check for a successful response (usually 204 No Content on success)
        if ($httpCode === 204) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Subscription has been canceled successfully.'
            ]);
        } else {
            echo json_encode([
                'error' => 'Error canceling subscription',
                'httpCode' => $httpCode,
                'response' => $response
            ]);
        }
    }

    function listSubscriptions($accessToken): void
    {

        $url = "https://api.sandbox.paypal.com/v1/billing/subscriptions";
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

        // Khởi tạo curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $subscriptions = json_decode($response, true);
        var_dump($subscriptions);
    }


    function reviseSubscription($accessToken)
    {
        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $licenseKey = isset($data['license_key']) ? $data['license_key'] : '';
        $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey);

        $planId = $data['planId'];
        $url = "https://api-m.sandbox.paypal.com/v1/billing/subscriptions/{$subscriptionId}/revise";

        // Dữ liệu để cập nhật gói
        $reviseData = [
            "plan_id" => $planId,
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
            "Authorization: Bearer {$accessToken}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reviseData));

        $response = curl_exec($ch);
        curl_close($ch);

        // Xử lý phản hồi
        $responseArray = json_decode($response, true);
        return json_encode([
            "success" => true,
            "message" => "Subscription revised successfully.",
            "data" => $responseArray,
        ]);
    }



    function generateLicenseKey()
    {
        return strtoupper(bin2hex(random_bytes(16)));
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
        ]);
    }
}
