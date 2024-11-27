<?php
require_once '../vendor/autoload.php';
require_once '../redis.php';
// require_once '../config.php';
// require_once '../common.php';
// require_once '../models/DB.php';
use App\Common;
use App\Config;
use App\Models\DB;
use App\Models\Subscription;

class PaypalWebhook
{
    private $apiKey;
    private $endpointSecret;
    private $bank_name;
    private $app_name;
    private $plan_id;
    private $access_token;

    /**
     * @param mixed $bank_name
     */
    public function setBankName($bank_name)
    {
        $this->bank_name = $bank_name;
    }

    /**
     * @param mixed $app_name
     */
    public function setAppName($app_name)
    {
        $this->app_name = $app_name;
    }

    /**
     * @param mixed $plan_id
     */
    public function setPlanId($plan_id)
    {
        $this->plan_id = $plan_id;
    }

    function initByBankName($bank_name)
    {
        $this->bank_name = $bank_name;

        $bankConfig = Config::$banks[$bank_name];
        if (!isset($bankConfig['api_key']) || !isset($bankConfig['secret_key'])) {
            die("Invalid config: {$bank_name}");
        }

        $this->apiKey = $bankConfig['api_key'];
        $this->endpointSecret = $bankConfig['secret_key'];
        $this->get_paypal_access_token();
    }

    function initByAppName($app_name)
    {
        if (!isset(Config::$apps[$app_name]['paypal'])) {
            die('Paypal config not found');
        }
        $this->app_name = $app_name;
        $this->bank_name = Config::$apps[$app_name]['paypal'];

        $bankConfig = Config::$banks[$this->bank_name];
        $this->apiKey = $bankConfig['api_key'];
        $this->endpointSecret = $bankConfig['secret_key'];

        $this->get_paypal_access_token();
    }

    private function get_paypal_access_token()
    {
        $client_id = $this->apiKey;
        $clientSecret = $this->endpointSecret;

        $url = "https://api.paypal.com/v1/oauth2/token";
        $headers = [
            "Authorization: Basic " . base64_encode("$client_id:$clientSecret"),
            "Content-Type: application/x-www-form-urlencoded"
        ];
        $data = "grant_type=client_credentials";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        $this->access_token = isset($result['access_token']) ? $result['access_token'] : '';
    }

    function createPaySessionPaypal($plan_alias)
    {
        $this->plan_id = @Config::$banks[$this->bank_name]['product_ids'][$this->app_name][$plan_alias];
        error_log($this->plan_id . ' ' . $this->bank_name . ' ' . $plan_alias . ' ' . $this->app_name);
        if (!$this->plan_id || $this->access_token) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'New plan and customer are required']);
            return;
        }

        $subscriptionData = [
            'plan_id' => $this->plan_id, // Plan ID from PayPal
            'auto_renewal' => true, // Auto renewal for the subscription
            'application_context' => [
                'brand_name' => 'RIVERNET',
                'locale' => 'en-US',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS', // Or 'NO_SHIPPING' if you don’t need shipping info
                'user_action' => 'SUBSCRIBE_NOW', // You can set this to 'CONTINUE' for subscription renewal
                'return_url' => Config::$web_domain . 'paypal/success', // Redirect URL after payment success
                'cancel_url' => Config::$web_domain, // Redirect URL if payment is cancelled
            ]
        ];

        $url = "https://api.paypal.com/v1/billing/subscriptions";

        $headers = [
            "Authorization: Bearer " . $this->access_token,
            "Content-Type: application/json"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subscriptionData));
        $response = curl_exec($ch);

        if (!$response) {
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

    // Hàm xử lý webhook từ PayPal
    function handlePaypalWebhook()
    {
        // Kiểm tra nếu phương thức là POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $payload = file_get_contents('php://input');

            $data = json_decode($payload, true);

            $event = $data['event_type'];

            try {
                switch ($event) {
                    case 'PAYMENT.SALE.COMPLETED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handlePaymentCompleted($data);
                        break;
                    case 'PAYMENT.SALE.PENDING': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handlePaymentPending($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.CREATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionCreated($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.UPDATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionUpdate($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.RE-ACTIVATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionReActivated($data);
                        break;
                    case 'BILLING.SUBSCRIPTION.ACTIVATED': //Xảy ra bất cứ khi nào nỗ lực thanh toán hóa đơn thành công.
                        $this->handleSubscriptionActivated($data);
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
        } else {
            // Nếu không phải là yêu cầu POST
            file_put_contents('log/webhook_debug.log', "Request method is not POST.\n\n", FILE_APPEND);
        }
    }

    function handleSubscriptionActivated($data)
    {
        // Ghi log sự kiện nhận webhook

        // Kiểm tra trạng thái và chuẩn bị thông tin từ webhook
        $status = $data['resource']['status'] == 'ACTIVE' ? 'active' : $data['resource']['status'];
        $subscription_id = $data['resource']['id'];
        $customer_email = $data['resource']['subscriber']['email_address'];
        $customer_name = $data['resource']['subscriber']['email_address'];

        // Gọi API PayPal để lấy chi tiết gói đăng ký
        $plan_id = $data['resource']['plan_id'];
        $planDetails = $this->getPlanDetailsFromPayPal($plan_id, $this->access_token);
        if (!$planDetails) {
            error_log("Failed to fetch plan details for plan_id: $plan_id");
            return;
        }

        // Tính toán ngày hết hạn của gói đăng ký
        $current_period_end = $this->calculatePeriodEnd($planDetails['billing_cycles'][0]);

        // Ghi log thời gian hết hạn
        $formatted_period_end = $current_period_end->format('Y-m-d H:i:s');


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
        $url = "https://api.paypal.com/v1/billing/plans/{$plan_id}";
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
        $db = new DB();
        $db->setTable('subscriptions');
        $itemUpdateSub = [
            'status' => $status,
            'current_period_end' => $current_period_end,
            'customer_email' => $customer_email
        ];
        $db->updateFields($itemUpdateSub, ['subscription_id' => $subscription_id]);
    }

    // Cập nhật email của khách hàng trong hóa đơn
    private function updateInvoiceEmail($subscription_id, $customer_email)
    {
        $db = new DB();
        $db->setTable('invoice');
        $db->updateFields(['customer_email' => $customer_email], ['subscription_id' => $subscription_id]);
    }

    // Kiểm tra và gửi license key nếu chưa gửi
    private function checkAndSendLicenseKey($subscription_id, $customer_email, $customer_name, $formatted_period_end)
    {
        $db = new DB();
        $db->setTable('licensekey');
        $itemSelectKeySend = [
            'license_key',
            'send',
        ];
        $result =  $db->selectRow($itemSelectKeySend, ['subscription_id' => $subscription_id]);


        if ($result && isset($result['license_key']) && $result['send'] === 'not') {
            $licenseKey = $result['license_key'];
            error_log("Sending license key to: $customer_email");
            $appNameEmail = $this->getCustomerAppNameBySubscriptionId($subscription_id);
            if ($appNameEmail == 'vidcombo') {
                // Gửi license key qua email
                $send_status = Common::sendLicenseKey($customer_email, $customer_name, $licenseKey);
            } else {
                $send_status =  Common::sendLicenseKeyEmailVidobo($customer_email, $customer_name, $licenseKey);
            }
            if ($send_status) {
                $this->updateLicenseKeySendStatus($subscription_id, $formatted_period_end);
            }
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }
    }

    private function updateLicenseKeySendStatus($subscription_id, $formatted_period_end)
    {
        $db = new DB();
        $db->setTable('licensekey');
        $db->updateFields(['send' => 'ok', 'current_period_end' => $formatted_period_end], ['subscription_id' => $subscription_id]);
    }







    //--------- Start funtion webhook --------------/


    function handleSubscriptionCreated($data)
    {
        $subscription = $data['resource'];


        $subscription_id = $data['resource']['id'];

        $status = $data['resource']['status'];
        $current_period_start_iso = $data['resource']['start_time'];
        $dateTime = new DateTime(datetime: $current_period_start_iso);
        $current_period_start = $dateTime->format('Y-m-d H:i:s');
        $create_time = $data['create_time'];
        $plan = $data['resource']['plan_id'];
        $subcrscription_json = json_encode($data);


        $banks_alis = Config::$banks[$this->bank_name] ?? null;

        $app_product = null;
        $plan_alias = null;

        foreach ($banks_alis['product_ids'] as $app => $plans) {
            foreach ($plans as $alias => $planId) {
                if ($planId === $plan) {
                    $plan_alias = $alias;
                    $app_product = $app;
                    error_log("[INFO] Found Plan Alias: $plan_alias, App Product: $app_product");
                    break 2;
                }
            }
        }


        $db = new DB();
        $db->setTable('subscriptions');
        $dataInsert = array(
            'subscription_id' => $subscription_id,
            'app_name' => $app_product,
            'status' => $status,
            'current_period_start' => $current_period_start,
            'create_time' => $create_time,
            'plan' => $plan,
            'subscription_json' => $subcrscription_json,
            'bank_name' => 'Paypal',
        );
        $db->insertFields($dataInsert);
    }
    function handlePaymentCompleted($data)
    {

        $subscription_id = $data['resource']['billing_agreement_id'];
        $create_time = $data['create_time'];
        $dbSub = new DB();
        $dbSub->setTable('subscriptions');
        $dataSelectSub = array(
            'customer_email',
            'customer_email',
            'current_period_end',
            'plan'
        );
        $subscription = $dbSub->selectRow($dataSelectSub, ['subscription_id' => $subscription_id]);


        $customer_email = $subscription['customer_email'];
        $customer_name = $subscription['customer_email'];
        $current_period_end = $subscription['current_period_end'];
        error_log('BankConfig for name: ' . print_r($data, true));

        $plan = $subscription['plan'];

        $banks_alis = Config::$banks[$this->bank_name] ?? null;
        if (!$banks_alis) {
            error_log("[ERROR] Bank configuration not found for: " . $this->bank_name);
            return;
        }

        $plan_alias = null;
        $app_product = null;


        foreach ($banks_alis['product_ids'] as $app => $plans) {
            foreach ($plans as $alias => $planId) {
                if ($planId === $plan) {
                    $plan_alias = $alias;
                    $app_product = $app;
                    error_log("[INFO] Found Plan Alias: $plan_alias, App Product: $app_product");
                    break 2;
                }
            }
        }

        if (!$plan_alias || !$app_product) {
            error_log("[ERROR] Plan alias or App Product not found for Plan ID: $plan");
        }

        $date = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $create_time);
        $formattedDateCreate_time = $date->format('Y-m-d H:i:s');
        $licenseKey = $this->generateLicenseKey();

        // $stmtCheck = $this->connection->prepare("SELECT COUNT(*) FROM `licensekey` WHERE `subscription_id` = :subscription_id");
        // $stmtCheck->execute([':subscription_id' => $subscription_id]);
        $dbkey = new DB();
        $dbkey->setTable('licensekey');
        $dataSelectSub = array(
            'licensekey',
        );
        $row = $dbkey->selectRow($dataSelectSub, ['subscription_id' => $subscription_id]);

        if ($row) {
            $current_period_end_date = new DateTime($current_period_end);
            error_log($plan);
            $url = "https://api.paypal.com/v1/billing/plans/{$plan}";
            $headers = [
                "Authorization: Bearer " . $this->access_token,
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


            $frequency = $planDetails['billing_cycles'][0]['frequency'];

            if (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'MONTH') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' month');
            } elseif (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'DAY') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' day');
            }



            $new_period_end = $current_period_end_date->format('Y-m-d H:i:s');
            $dbInsetkey = new DB();
            $dbInsetkey->setTable('licensekey');
            $dataInsertKey = array(
                'subscription_id' => $subscription_id,
                'license_key' => $licenseKey,
                'status' => 'active',
                'send' => 'not',
                'plan' => $plan,
                'plan_alias' => $plan_alias,
                'sk_key' => $this->apiKey,
                'sign_key' => $this->endpointSecret,
                'created_at' => $formattedDateCreate_time,
                'current_period_end' => $new_period_end,
            );
            $dbInsetkey->insertFields($dataInsertKey);
        } else {
            // Gia hạn
            $current_period_end_date = new DateTime($current_period_end); // Ngày kết thúc hiện tại


            $url = "https://api.paypal.com/v1/billing/plans/{$plan}";
            $headers = [
                "Authorization: Bearer " . $this->access_token,
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

            $frequency = $planDetails['billing_cycles'][0]['frequency'];

            if (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'MONTH') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' month');
            } elseif (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'DAY') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' day');
            }


            // Cập nhật lại ngày hết hạn
            $new_period_end = $current_period_end_date->format('Y-m-d H:i:s');

            $updateSub = new DB();
            $updateSub->setTable('subscriptions');
            $updateSubdata = [
                'current_period_end' => $new_period_end,
            ];
            $updateSub->updateFields($updateSubdata, ['subscription_id' => $subscription_id]);



            $updateKey = new DB();
            $updateKey->setTable('licensekey');
            $updateKeydata = [
                'current_period_end' => $new_period_end,
            ];
            $updateKey->updateFields($updateKeydata, ['subscription_id' => $subscription_id]);
        }


        $insetInvoice = new DB();
        $insetInvoice->setTable('invoice');
        $invoiceData = [
            'invoice_id' => $data['id'],
            'status' => 'paid',
            'customer_email' => $customer_email,
            'payment_intent' => $data['resource']['id'],
            'period_end' => strtotime($current_period_end),
            'period_start' => strtotime($data['create_time']),
            'subscription_id' => $subscription_id,
            'currency' => $data['resource']['amount']['currency'],
            'amount_due' => $data['resource']['amount']['total'],
            'created' => strtotime($data['create_time']),
            'amount_paid' => $data['resource']['amount']['details']['subtotal'],
            'invoice_datetime' => $formattedDateCreate_time,
        ];
        $insetInvoice->insertFields($invoiceData);

        $amount_due = $data['resource']['amount']['total'];
        $invoiced_date =  strtotime($data['create_time']);

        $selectLiKey = new DB();
        $selectLiKey->setTable('licensekey');
        $dataLiKey = [
            'license_key',
            'send'
        ];
        $result =  $selectLiKey->selectRow($dataLiKey, ['subscription_id' => $subscription_id]);

        if ($this->app_name == 'vidcombo') {
            Common::sendSuccessEmailVidcombo($customer_email, $customer_name, $amount_due, $invoiced_date);
        } else {
            Common::sendSuccessEmailVidobo($customer_email, $customer_name, $amount_due, $invoiced_date);
        }

        // Common::sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date);
        if ($result && isset($result['license_key']) && $result['send'] === 'not') {
            $licenseKey = $result['license_key'];
            error_log("Sending license key to: $customer_email");

            // Gửi license key qua email
            if ($this->app_name == 'vidcombo') {
                $send_status = Common::sendLicenseKey($customer_email, $customer_name, $licenseKey);
            } else {
                $send_status = Common::sendLicenseKeyEmailVidobo($customer_email, $customer_name, $licenseKey);
            }
            // $send_status = Common::sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey);

            if ($send_status) {
                $updateLiKey = new DB();
                $updateLiKey->setTable('licensekey');
                $dataLiKeyupdate = [
                    'send' => 'ok',
                    'subscription_id' => $subscription_id,
                ];
                $updateLiKey->updateFields($dataLiKeyupdate, ['subscription_id' => $subscription_id]);
            }
        } else {
            error_log("No license key found for subscription ID: $subscription_id");
        }
        error_log('paymentCOmple');
    }
    function handleSubscriptionUpdate($data)
    {

        $subscription_id = $data['resource']['id'];
        $plan = $data['resource']['plan_id'];


        $banks_alis = Config::$banks[$this->bank_name] ?? null;
        if (!$banks_alis) {
            error_log("[ERROR] Bank configuration not found for: " . $this->bank_name);
            return;
        }

        $plan_alias = null;
        $app_product = null;


        foreach ($banks_alis['product_ids'] as $app => $plans) {
            foreach ($plans as $alias => $planId) {
                if ($planId === $plan) {
                    $plan_alias = $alias;
                    $app_product = $app;
                    error_log("[INFO] Found Plan Alias: $plan_alias, App Product: $app_product");
                    break 2;
                }
            }
        }


        if (!$plan_alias || !$app_product) {
            error_log("[ERROR] Plan alias or App Product not found for Plan ID: $plan");
        }
        $updateSub = new DB();
        $updateSub->setTable('subscriptions');
        $dataSubupdate = [
            'plan' => $plan,
        ];
        $updateSub->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);

        // $stmtUpdate = $this->connection->prepare("UPDATE `subscriptions` SET `plan` = :plan WHERE `subscription_id` = :subscription_id");
        // $stmtUpdate->execute([
        //     ':subscription_id' => $subscription_id,
        //     ':plan' => $plan,
        // ]);
        $updateKey = new DB();
        $updateKey->setTable('licensekey');
        $dataKeyupdate = [
            'plan' => $plan,
            'plan_alias' => $plan_alias,
        ];
        $updateKey->updateFields($dataKeyupdate, ['subscription_id' => $subscription_id]);
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

        $updateSub = new DB();
        $updateSub->setTable('subscriptions');
        $dataSubupdate = [
            'current_period_end' => $current_period_end,
        ];
        $updateSub->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);

        $updateKey = new DB();
        $updateKey->setTable('licensekey');
        $dataKeyupdate = [
            'current_period_end' => $current_period_end,
        ];
        $updateKey->updateFields($dataKeyupdate, ['subscription_id' => $subscription_id]);
    }



    function handleSubscriptionReActivated($data)
    {

        $subscription_id = $data['resource']['id'];
        $current_period_end_iso = $data['resource']['agreement_details']['next_billing_date'];
        if ($data['resource']['state'] == 'Active') {
            $status = 'active';
        }
        $dateTime = new DateTime($current_period_end_iso);
        $current_period_end = $dateTime->format('Y-m-d H:i:s');


        $updateSub = new DB();
        $updateSub->setTable('subscriptions');
        $dataSubupdate = [
            'status' => $status,
            'current_period_end' => $current_period_end,
        ];
        $updateSub->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);


        $updateKey = new DB();
        $updateKey->setTable('licensekey');
        $dataKeyupdate = [
            'status' => $status,
            'current_period_end' => $current_period_end,
        ];
        $updateKey->updateFields($dataKeyupdate, ['subscription_id' => $subscription_id]);
    }
    function handleSubscriptionCancelled($data)
    {
        $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handleSubscriptionCancelled.log', $logContent, FILE_APPEND);
        $subscription_id = $data['resource']['id'];
        $status = $data['resource']['status'];
        if ($status === 'CANCELLED') {
            $status = 'canceled';

            $updateSub = new DB();
            $updateSub->setTable('subscriptions');
            $dataSubupdate = [
                'status' => $status,
            ];
            $updateSub->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);
        }
    }



    // ----- End webhooks funtion  ------- //



    function upSubscription($accessToken)
    {
        $body = file_get_contents('php://input');
        parse_str($body, result: $data);
        $subscription_id = $data['subscription_id'];
        $planId = $data['planId'];
        $url = "https:///api.paypal.com/v1/billing/subscriptions/{$subscription_id}";

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
        $url = "https:///api.paypal.com/v1/catalogs/products";

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
        $url = "https:///api.paypal.com/v1/catalogs/products";
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
        $deleteUrl = "https:///api.paypal.com/v1/catalogs/products/{$product_id}";
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
        $url = "https:///api.paypal.com/v1/billing/plans";
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
        $url = "https:///api.paypal.com/v1/billing/plans";

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
        $url = "https:///api.paypal.com/v1/billing/plans/{$planId}";

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
        $url = "https:///api.paypal.com/v1/billing/plans";

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
                $deleteUrl = "https:///api.paypal.com/v1/billing/plans/{$planId}";

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
        parse_str($body, $data);
        $subscriptionId = $data['subscriptionId'];
        $url = "https:///api.paypal.com/v1/billing/subscriptions/$subscriptionId"; // Replace with live API endpoint for production

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

    function cancelSubscription($accessToken): void
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
        $url = "https://api.paypal.com/v1/billing/subscriptions/{$subscriptionId}/cancel";

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

        $url = "https://api.paypal.com/v1/billing/subscriptions";
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
        $url = "https:///api.paypal.com/v1/billing/subscriptions/{$subscriptionId}/revise";

        // Dữ liệu để cập nhật gói
        $reviseData = [
            "plan_id" => $planId,
            "application_context" => [
                "brand_name" => "RIVERNET",
                "locale" => "en-US",
                "shipping_preference" => "NO_SHIPPING",
                "user_action" => "SUBSCRIBE_NOW",
                "return_url" =>   Config::$web_domain . "paypal/success",
                "cancel_url" =>   Config::$web_domain
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
        $selectSub = new DB();
        $selectSub->setTable('subscriptions');
        $dataSubupdate = [
            'subscription_id'
        ];
        $device = $selectSub->selectRow($dataSubupdate, ['license_key' => $licenseKey]);

        // $stmt = $this->connection->prepare("SELECT `subscription_id` FROM `licensekey` WHERE `license_key` = :license_key");
        // $stmt->execute([':license_key' => $licenseKey]);
        // $device = $stmt->fetch(PDO::FETCH_ASSOC);

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
    function getCustomerAppNameBySubscriptionId($subscription_id)
    {
        try {
            $selectSub = new DB();
            $selectSub->setTable('subscriptions');
            $dataSubupdate = [
                'app_name'
            ];
            $result = $selectSub->selectRow($dataSubupdate, ['subscription_id' => $subscription_id]);


            return isset($result['app_name']) ? $result['app_name'] : null;
        } catch (PDOException $e) {
            error_log("Error occurred in getCustomerEmailBySubscriptionId: " . $e->getMessage());
            return null;
        }
    }
    function updateSubPaypal($licenseKey, $convertname, $plan)
    {

        $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey);
        $appNameupdateSup = $this->findAppNametoSubcritpion($subscriptionId);
        $nameBankApp = Config::$banks[$appNameupdateSup][$convertname];
        $planKey = Config::$banks[$nameBankApp]['product_ids'][$appNameupdateSup][$plan];

        $url = "https:///api.paypal.com/v1/billing/subscriptions/{$subscriptionId}/revise";

        // Dữ liệu để cập nhật gói
        $reviseData = [
            "plan_id" => $planKey,
            "application_context" => [
                "brand_name" => "RIVERNET",
                "locale" => "en-US",
                "shipping_preference" => "NO_SHIPPING",
                "user_action" => "SUBSCRIBE_NOW",
                "return_url" =>  Config::$web_domain . "paypal/success",
                "cancel_url" =>  Config::$web_domain
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
            error_log('eror_log');
        }
    }
    function findAppNametoSubcritpion($subscriptionId)
    {

        // Chuẩn bị và thực hiện truy vấn
        $selectSub = new DB();
        $selectSub->setTable('subscriptions');
        $dataSubupdate = [
            'app_name'
        ];
        $device = $selectSub->selectRow($dataSubupdate, ['subscription_id' => $subscriptionId]);
        if (isset($device['app_name']) && $device['app_name'])
            return $device['app_name'];
        return null;
    }
}
