<?php
require_once '../vendor/autoload.php';
//require_once '../redisCache.php';
 require_once '../config.php';
 require_once '../common.php';
 require_once '../models/DB.php';
use App\Common;
use App\Config;
use App\Models\DB;


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
        error_log('key ' . $this->apiKey . 'endpoint secret ' . $this->endpointSecret);
        $this->get_paypal_access_token();
    }

    private function get_paypal_access_token()
    {
        $client_id = $this->apiKey;
        $clientSecret = $this->endpointSecret;

        $url = "https://api-m.paypal.com/v1/oauth2/token";
        $headers = [
            "Authorization: Basic " . base64_encode("$client_id:$clientSecret"),
            "Content-Type: application/x-www-form-urlencoded"
        ];
        $data =  "grant_type=client_credentials";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response);
        $this->access_token = isset($result->access_token) ? $result->access_token : '';
    }

    function createPaySessionPaypal($plan_alias)
    {

        // Kiểm tra các thông tin cần thiết
        $this->plan_id = @Config::$banks[$this->bank_name]['product_ids'][$this->app_name][$plan_alias];


        // Chuẩn bị dữ liệu cho Subscription
        $subscriptionData = [
            'plan_id' => $this->plan_id, // ID của gói đăng ký
            'auto_renewal' => true, // Tự động gia hạn
            'application_context' => [
                'brand_name' => 'VIDCOMBO', // Tên thương hiệu hiển thị
                'locale' => 'en-US', // Ngôn ngữ giao diện
                'shipping_preference' => 'NO_SHIPPING', // Hoặc 'NO_SHIPPING' nếu không yêu cầu địa chỉ giao hàng
                'user_action' => 'SUBSCRIBE_NOW', // Hành động mặc định
                'return_url' => Config::$web_domain . 'paypal/success', // URL khi thanh toán thành công
                'cancel_url' => Config::$web_domain, // URL khi thanh toán bị hủy
            ]
        ];

        // Endpoint PayPal cho Sandbox
        $url = "https://api-m.paypal.com/v1/billing/subscriptions";

        // Gửi yêu cầu API đến PayPal
        $headers = [
            "Authorization: Bearer " . $this->access_token,
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

        if(@$_SERVER['REMOTE_ADDR'] == '14.232.244.3'){
            echo '<pre>'; print_r($response); echo '</pre>';
            die;
        }

        $subscriptionResponse = json_decode($response, true);


        if (isset($subscriptionResponse['id'])) {
            echo json_encode([
                'subscription_id' => $subscriptionResponse['id'], // ID của subscription
                'status' => $subscriptionResponse['status'], // Trạng thái đăng ký
                'approval_url' => $subscriptionResponse['links'][0]['href'] ?? null // URL để khách hàng chấp nhận
            ]);
        } else {
            echo json_encode([
                'error' => 'Error creating subscription',
                'message' => $subscriptionResponse['message'] ?? 'Unknown error'
            ]);
        }
    }

    function CallAPI($url, $method, $args)
    {
        $curl = curl_init($url);

        $headers = isset($args['header']) ? $args['header'] : array();
        $data = isset($args['body']) ? $args['body'] : array();

        $headers = $headers ?: array('Content-Type: application/json', 'Authorization: Bearer ' . $this->access_token);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        switch ($method) {
            case "GET":
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
                break;
            case "POST":
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            case "DELETE":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
        }
        $response = curl_exec($curl);
        // $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // switch ($httpCode) {
        //     case 200:
        //     case 201:
        //         curl_close($curl);
        //         return ($response);
        //     case 404:
        //         $error_status = "404: API Not found";
        //         break;
        //     case 500:
        //         $error_status = "500: servers replied with an error.";
        //         break;
        //     case 502:
        //         $error_status = "502: servers may be down or being upgraded. Hopefully they'll be OK soon!";
        //         break;
        //     case 503:
        //         $error_status = "503: service unavailable. Hopefully they'll be OK soon!";
        //         break;
        //     default:
        //         $error_status = "Undocumented error: " . $httpCode . " : " . curl_error($curl);
        //         break;
        // }
        curl_close($curl);
        return $response;
        // echo '<pre>'; print_r($error_status); echo '</pre>'; die;

    }

    // Hàm xử lý webhook từ PayPal
    function handlePaypalWebhook()
    {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        $event = @$data['event_type'];

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
        $this->checkAndSendLicenseKey($subscription_id, $customer_email, $customer_name, $formatted_period_end);

        error_log('Subscription activated successfully');
    }

    // Lấy chi tiết plan từ PayPal API
    private function getPlanDetailsFromPayPal($plan_id, $accessToken)
    {
        $url = "https://api-m.paypal.com/v1/billing/plans/{$plan_id}";
        $response = $this->CallAPI($url, 'GET', array());
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
        $dateTime = new DateTime($current_period_start_iso);
        $current_period_start = $dateTime->format('Y-m-d H:i:s');
        $create_time = $data['create_time'];
        $plan_id = $data['resource']['plan_id'];
        $subcrscription_json = json_encode($data);

        list($this->app_name, $plan_alias) = Config::getAppNamePlanAliasByPlanID($plan_id);

        $db = new DB();
        $db->setTable('subscriptions');

        $dataInsert = array(
            'subscription_id' => $subscription_id,
            'customer_id' => '',
            'status' => $status,
            'current_period_start' => $current_period_start,
            'current_period_end' => $current_period_start,
            'customer' => '',
            'subscription_json' => $subcrscription_json,
            'plan' => $plan_id,
            'bank_name' => 'Paypal',
            'app_name' => $this->app_name,
        );


        $db->insertFields($dataInsert);
    }
    private $db;
    function getDB()
    {
        if ($this->db) return $this->db;
        $this->db = new DB();
        return $this->db;
    }
    function handlePaymentCompleted($data)
    {
        $subscription_id = $data['resource']['billing_agreement_id'];
        $create_time = $data['create_time'];
        $db_connector = $this->getDB();
        $db_connector->setTable('subscriptions');
        $subscription = $db_connector->selectRow('*', ['subscription_id' => $subscription_id]);

        $customer_email = $subscription['customer_email'];
        $customer_name = $subscription['customer_email'];
        $current_period_end = $subscription['current_period_end'];
        $this->app_name = $subscription['app_name'];
        $this->plan_id = $subscription['plan'];

        $plan_alias = Config::getPlanAliasByPlanID($this->plan_id);

        $date = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $create_time);
        $formattedDateCreate_time = $date->format('Y-m-d H:i:s');
        $licenseKey = $this->generateLicenseKey();

        $invoice_id = $data['id'];
        $payment_intent =  $data['resource']['id'];
        $period_end = strtotime($current_period_end);
        $period_start = strtotime($data['create_time']);
        $currency = $data['resource']['amount']['currency'];
        $amount_due = $data['resource']['amount']['total'];
        $created = $data['create_time'];
        $amount_paid = $data['resource']['amount']['details']['subtotal'];

        $db_connector->setTable('licensekey');
        $row = $db_connector->countRecords(['subscription_id' => $subscription_id]);

        if ($row == 0) {
            $current_period_end_date = new DateTime($current_period_end);

            $url = "https://api.sandbox.paypal.com/v1/billing/plans/" . $this->plan_id;
            error_log('urrl completed' . $url);
            $headers = [
                "Authorization: Bearer " . $this->access_token,
                "Content-Type: application/json"
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $planDetails = json_decode($response, true);

            $frequency = $planDetails['billing_cycles'][0]['frequency'];

            if (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'MONTH') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' month');
            } elseif (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'DAY') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' day');
            }

            $new_period_end = $current_period_end_date->format('Y-m-d H:i:s');
            $db_connector->setTable('licensekey');
            $dataInsertKey = array(
                'customer_id' => '',
                'status' => 'active',
                'subscription_id' => $subscription_id,
                'license_key' => $licenseKey,
                'send' => 'not',
                'current_period_end' => $new_period_end,
                'plan' => $this->plan_id,
                'created_at' => $formattedDateCreate_time,
                'plan_alias' => $plan_alias,
                'sk_key' => $this->apiKey,
                'sign_key' => $this->endpointSecret,
            );
            $db_connector->insertFields($dataInsertKey);
        }
        else {
            // Gia hạn
            $current_period_end_date = new DateTime($current_period_end); // Ngày kết thúc hiện tại


            $url = "https://api-m.paypal.com/v1/billing/plans/" . $this->plan_id;
            $dataPost = array('header' => array(), 'body' => '');
            $response = $this->CallAPI($url, 'GET', $dataPost);
            $planDetails = json_decode($response, true);

            $frequency = $planDetails['billing_cycles'][0]['frequency'];

            if (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'MONTH') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' month');
            } elseif (isset($frequency['interval_unit']) && $frequency['interval_unit'] == 'DAY') {
                $current_period_end_date->modify('+' . $frequency['interval_count'] . ' day');
            }


            // Cập nhật lại ngày hết hạn
            $new_period_end = $current_period_end_date->format('Y-m-d H:i:s');

            $db_connector->setTable('subscriptions');
            $updateSubdata = [
                'status' => 'active',
                'current_period_end' => $new_period_end,
            ];
            $db_connector->updateFields($updateSubdata, ['subscription_id' => $subscription_id]);


            $db_connector->setTable('licensekey');
            $updateKeydata = [
                'status' => 'active',
                'current_period_end' => $new_period_end,
            ];
            $db_connector->updateFields($updateKeydata, ['subscription_id' => $subscription_id]);
        }

        $db_connector->setTable('invoice');
        $count = $db_connector->countRecords(['invoice_id' => $invoice_id]);
        if (!$count){
            $this->insertInvoices($invoice_id, $customer_email, $payment_intent, $period_end, $period_start, $subscription_id, $currency, $amount_due, $created, $amount_paid, $formattedDateCreate_time);
        }

        $amount_due = $data['resource']['amount']['total'];
        $invoiced_date =  strtotime($data['create_time']);

        $db_connector->setTable('licensekey');
        $result =  $db_connector->selectRow('*', ['subscription_id' => $subscription_id]);

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
                $db_connector->setTable('licensekey');
                $dataLiKeyupdate = [
                    'send' => 'ok',
                    'subscription_id' => $subscription_id,
                ];
                $db_connector->updateFields($dataLiKeyupdate, ['subscription_id' => $subscription_id]);
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

        list($this->app_name, $plan_alias) = Config::getAppNamePlanAliasByPlanID($plan);


        if (!$plan_alias || !$this->app_name) {
            error_log("[ERROR] Plan alias or App Product not found for Plan ID: $plan . " . $this->app_name);
        }
        $db_connector = $this->getDB();
        $db_connector->setTable('subscriptions');
        $dataSubupdate = [
            'plan' => $plan,
        ];
        $db_connector->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);

        $db_connector->setTable('licensekey');
        $dataKeyupdate = [
            'plan' => $plan,
            'plan_alias' => $plan_alias,
        ];
        $db_connector->updateFields($dataKeyupdate, ['subscription_id' => $subscription_id]);
    }
    function handlePaymentPending($data)
    {
        $logContent = "Webhook event received:\n" . print_r($data, true) . "\n\n";
        file_put_contents('log/handlePaymentPending.log', $logContent, FILE_APPEND);
    }

    function handleSubscriptionRenewed($data)
    {
        $subscription_id = $data['resource']['id']; // ID của subscription
        $nextBillingTime = $data['resource']['billing_info']['next_billing_time']; // Ngày gia hạn tiếp theo
        $dateTime = new DateTime($nextBillingTime);
        $current_period_end = $dateTime->format('Y-m-d H:i:s');
        $db_connector = new DB();
        $db_connector->setTable('subscriptions');
        $dataSubupdate = [
            'current_period_end' => $current_period_end,
        ];
        $db_connector->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);

        $db_connector->setTable('licensekey');
        $dataKeyupdate = [
            'current_period_end' => $current_period_end,
        ];
        $db_connector->updateFields($dataKeyupdate, ['subscription_id' => $subscription_id]);
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


        $db_connector = new DB();
        $db_connector->setTable('subscriptions');
        $dataSubupdate = [
            'status' => $status,
            'current_period_end' => $current_period_end,
        ];
        $db_connector->updateFields($dataSubupdate, ['subscription_id' => $subscription_id]);


        $db_connector->setTable('licensekey');
        $dataKeyupdate = [
            'status' => $status,
            'current_period_end' => $current_period_end,
        ];
        $db_connector->updateFields($dataKeyupdate, ['subscription_id' => $subscription_id]);
    }
    function handleSubscriptionCancelled($data)
    {

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


    function listProducts()
    {
        $url = "https://api-m.paypal.com/v1/catalogs/products";

        $args = [
            'header' => [
                "Authorization: Bearer " . $this->access_token,
                "Content-Type: application/json"
            ]
        ];

        try {
            $response = $this->CallAPI($url, "GET", $args);
            $json = json_encode($response);
            echo json_decode($json, true);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }



    function createProduct()
    {
        $url = "https://api-m.paypal.com/v1/catalogs/products";

        $args = [
            'header' => [
                "Authorization: Bearer " . $this->access_token,
                "Content-Type: application/json"
            ],
            'body' => [
                "name" => "Vidobo",
                "description" => "Vidobo premium",
                "type" => "SERVICE"
            ]
        ];

        try {
            // Gọi API thông qua hàm CallAPI
            $response = $this->CallAPI($url, "POST", $args);

            // Kiểm tra và trả về phản hồi
            $result = json_decode($response, true);
            if (isset($result['id'])) {
                echo json_encode(["message" => "Product created successfully!", "product" => $result], JSON_PRETTY_PRINT);
            } else {
                echo json_encode(["message" => "Failed to create product.", "error" => $result], JSON_PRETTY_PRINT);
            }
        } catch (Exception $e) {
            // Xử lý lỗi nếu xảy ra ngoại lệ
            echo json_encode(["error" => $e->getMessage()]);
        }
    }



    function createPlans($product_id, $plan_name, $number_month, $value_price, $plan_desc)
    {
        $url = "https://api-m.paypal.com/v1/billing/plans";

        $planData = [
            "product_id" => $product_id,
            "name" => $plan_name,
            "description" => $plan_desc,
            "billing_cycles" => [
                [
                    "frequency" => [
                        "interval_unit" => "MONTH",
                        "interval_count" => (int)$number_month,
                    ],
                    "tenure_type" => "REGULAR",
                    "sequence" => 1,
                    "total_cycles" => 0,
                    "pricing_scheme" => [
                        "fixed_price" => [
                            "value" => $value_price,
                            "currency_code" => "USD"
                        ]
                    ]
                ]
            ],
            "payment_preferences" => [
                "auto_bill_outstanding" => true,
                "payment_failure_threshold" => 3
            ],
            "taxes" => [
                "percentage" => "0",
                "inclusive" => false
            ]
        ];

        $args = [
            'header' => [
                "Authorization: Bearer " . $this->access_token,
                "Content-Type: application/json"
            ],
            'body' => $planData
        ];

        try {

            $response = $this->CallAPI($url, "POST", $args);
            $responseData = json_decode($response, true);


            if (isset($responseData['id'])) {
                echo json_encode(["message" => "Plan Created Successfully.", "plan_id" => $responseData['id']]);
            } else {
                echo json_encode(["error" => "Failed to create plan.", "details" => $responseData]);
            }
        } catch (Exception $e) {

            echo json_encode(["error" => $e->getMessage()]);
        }
    }


    function listPlans()
    {
        $url = "https://api-m.paypal.com/v1/billing/plans";

        $args = [
            'header' => [
                "Authorization: Bearer " . $this->access_token,
                "Content-Type: application/json"
            ]
        ];

        try {
            $response = $this->CallAPI($url, "GET", $args);
            $plans = json_decode($response, true);

            echo '<pre>'; print_r($plans); echo '</pre>';
            die;

            if (isset($plans['plans']) && count($plans['plans']) > 0) {
                echo json_encode($plans['plans'], JSON_PRETTY_PRINT);
            } else {
                echo json_encode(["message" => "No plans found."]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }



    function getPlanDetails()
    {
        $body = file_get_contents('php://input');
        parse_str($body, $data);

        if (empty($data['planId'])) {
            echo json_encode(["error" => "Plan ID is required."]);
            return;
        }

        $planId = $data['planId'];
        $url = "https://api-m.paypal.com/v1/billing/plans/{$planId}";

        $args = [
            'header' => [
                "Authorization: Bearer " . $this->access_token,
                "Content-Type: application/json"
            ]
        ];

        try {
            // Gửi yêu cầu GET để lấy chi tiết Plan
            $response = $this->CallAPI($url, "GET", $args);
            $planDetails = json_decode($response, true);

            if (isset($planDetails['id'])) {
                $responseData = [
                    "Plan ID" => $planDetails['id'],
                    "Name" => $planDetails['name'],
                    "Description" => $planDetails['description'],
                    "Status" => $planDetails['status'],
                ];

                // Thêm thông tin Billing Cycles
                if (!empty($planDetails['billing_cycles'])) {
                    $billingCycles = [];
                    foreach ($planDetails['billing_cycles'] as $cycle) {
                        $billingCycles[] = [
                            "Billing Cycle Frequency" => $cycle['frequency']['interval_count'] . " " . $cycle['frequency']['interval_unit'],
                            "Total Cycles" => $cycle['total_cycles'] ?? 'N/A',
                            "Pricing" => $cycle['pricing_scheme']['fixed_price']['value'] . " " . $cycle['pricing_scheme']['fixed_price']['currency_code']
                        ];
                    }
                    $responseData['Billing Cycles'] = $billingCycles;
                }

                // Thêm thông tin Payment Preferences
                if (!empty($planDetails['payment_preferences'])) {
                    $responseData['Payment Preferences'] = [
                        "Auto Bill Outstanding" => $planDetails['payment_preferences']['auto_bill_outstanding'] ? 'Yes' : 'No',
                        "Payment Failure Threshold" => $planDetails['payment_preferences']['payment_failure_threshold']
                    ];
                }

                // Thêm thông tin Taxes
                if (!empty($planDetails['taxes'])) {
                    $responseData['Taxes'] = [
                        "Tax Percentage" => $planDetails['taxes']['percentage'] . "%",
                        "Tax Inclusive" => $planDetails['taxes']['inclusive'] ? 'Yes' : 'No'
                    ];
                }

                // Trả về thông tin Plan dưới dạng JSON
                echo json_encode($responseData, JSON_PRETTY_PRINT);
            } else {
                echo json_encode(["message" => "Plan not found or error retrieving plan details."]);
            }
        } catch (Exception $e) {
            // Trả về lỗi khi có ngoại lệ
            echo json_encode(["error" => $e->getMessage()]);
        }
    }



    function getSubscriptionStatus()
    {
        $body = file_get_contents('php://input');
        parse_str($body, $data);

        if (empty($data['subscriptionId'])) {
            echo json_encode(["error" => true, "message" => "subscriptionId is required"]);
            return;
        }

        $subscriptionId = $data['subscriptionId'];
        $url = "https://api-m.paypal.com/v1/billing/subscriptions/$subscriptionId";

        $headers = [
            "Authorization: Bearer " . $this->access_token,
            "Content-Type: application/json"
        ];

        $response = $this->CallAPI($url, "GET", [
            'header' => $headers
        ]);
        $subscriptionData = json_decode($response, true);

        if (isset($subscriptionData['error'])) {
            echo json_encode(["error" => true, "message" => $subscriptionData['message'] ?? 'Unknown error']);
            return;
        }

        if (isset($subscriptionData['id'])) {
            echo json_encode([
                'subscription_id' => $subscriptionData['id'],
                'status' => $subscriptionData['status'],
                'status_update_time' => $subscriptionData['status_update_time'] ?? 'N/A',
                'plan_id' => $subscriptionData['plan_id'],
                'subscriber_email' => $subscriptionData['subscriber']['email_address'] ?? 'N/A',
                'create_time' => $subscriptionData['create_time'],
                'links' => $subscriptionData['links'] ?? [],
                'billing_info' => $subscriptionData['billing_info'] ?? [],
                'shipping_address' => $subscriptionData['shipping_address'] ?? [],
                'plan' => $subscriptionData['plan'] ?? [],
            ]);
        } else {
            echo json_encode([
                'error' => true,
                'message' => $subscriptionData['message'] ?? 'Unknown error retrieving subscription details'
            ]);
        }
    }


    function cancelSubscription()
    {

        $body = file_get_contents('php://input');
        parse_str($body, $data);
        $subscriptionId = $data['subscriptionId'] ?? null;

        if (!$subscriptionId) {
            echo json_encode(["error" => "Subscription ID is required"]);
            return;
        }

        $url = "https://api-m.paypal.com/v1/billing/subscriptions/{$subscriptionId}/cancel";

        $headers = [
            "Authorization: Bearer " . $this->access_token,
            "Content-Type: application/json"
        ];

        $data = [
            'reason' => 'User requested cancellation'
        ];

        $response = $this->CallAPI($url, "POST", [
            'header' => $headers,
            'body' => $data
        ]);


        echo json_encode([
            'status' => 'success',
            'message' => 'Subscription has been canceled successfully.'
        ]);
    }






    function reviseSubscription($licenseKey, $convertname, $plan)
    {
        // Fetch Subscription ID based on License Key
        $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey);
        $appNameupdateSup = $this->findAppNametoSubcritpion($subscriptionId); // Get app_name
        $nameBankApp = Config::$apps[$appNameupdateSup][$convertname]; // Get name from bankConfig
        $planKey = Config::$banks[$nameBankApp]['product_ids'][$appNameupdateSup][$plan]; // Get planKey

        // Log app name (for debugging purposes)
        error_log($appNameupdateSup);

        // URL for PayPal sandbox environment
        $url = "https://api-m.paypal.com/v1/billing/subscriptions/{$subscriptionId}/revise";

        // Data for revising the subscription
        $reviseData = [
            "plan_id" => $planKey,
            "application_context" => [
                "brand_name" => "RIVERNET",
                "locale" => "en-US",
                "shipping_preference" => "NO_SHIPPING",
                "user_action" => "SUBSCRIBE_NOW",
                "return_url" => Config::$web_domain . "paypal/success",
                "cancel_url" => Config::$web_domain
            ]
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->access_token // Ensure access token is valid
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reviseData));


        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        $responseArray = json_decode($response, true);

        if (isset($responseArray['error']) && $responseArray['error'] == 'invalid_token') {
            // Handle invalid token error
            error_log('Error revising subscription: Invalid Token');
            echo json_encode([
                "success" => false,
                "message" => "Invalid access token. Please refresh the token.",
                "data" => $responseArray
            ]);
        } else {
            // Success response
            echo json_encode([
                "session" => [
                    "url" => $responseArray['links'][0]['href'],
                ]

            ]);
        }
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
        $selectSub->setTable('licensekey');
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

        $subscriptionId = $this->findSubscriptionIdByLicenseKey($licenseKey); //Lấy ra subid từ bảng licensekey
        $appNameupdateSup = $this->findAppNametoSubcritpion($subscriptionId); //lấy ra app_name "vidcombo,vidobo"
        $nameBankApp = Config::$apps[$appNameupdateSup][$convertname]; //lấy ra được thuộc name nào trong bankConfig
        $planKey = Config::$banks[$nameBankApp]['product_ids'][$appNameupdateSup][$plan];


        var_dump($planKey . $subscriptionId);

        // $nameBankApp = Config::$banks[$appNameupdateSup][$convertname];
        // $planKey = Config::$banks[$nameBankApp]['product_ids'][$appNameupdateSup][$plan];

        $url = "https://api-m.sanbox.paypal.com/v1/billing/subscriptions/{$subscriptionId}/revise";

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
            "Authorization: Bearer " . $this->access_token
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reviseData));

        $response = curl_exec($ch);
        curl_close($ch);

        $responseArray = json_decode($response, true);
        var_dump($responseArray);

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
    function insertInvoices($invoiceId, $custommer_email, $payment_intent, $period_end, $period_start, $subscription_id, $currency, $amount_due, $created, $amount_paid, $invoice_dateme)
    {
        $insetInvoice = new DB();
        $insetInvoice->setTable('invoice');
        $invoiceData = [
            'invoice_id' => $invoiceId,
            'status' => 'paid',
            'customer_email' => $custommer_email,
            'payment_intent' => $payment_intent,
            'period_end' => $period_end,
            'period_start' => $period_start,
            'subscription_id' => $subscription_id,
            'currency' => $currency,
            'amount_due' => $amount_due,
            'created' => strtotime($created),
            'customer_id' => '',
            'amount_paid' => $amount_paid,
            'invoice_datetime' => $invoice_dateme,
        ];
        $insetInvoice->insertFields($invoiceData);
    }
}
