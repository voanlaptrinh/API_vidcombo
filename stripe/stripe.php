<?php
// require_once '../redis.php';
// require_once '../common.php';
require_once '../vendor/autoload.php';

// require_once '../config.php';

// require_once '../models/DB.php';

use PHPMailer\PHPMailer\Exception;
use Stripe\Stripe;
use App\Models\DB;
use App\Config;
// use App\Models\RedisCache;
use App\Models\Licensekey;
use App\Common;

class StripeWebhook
{
    private $connection;
    private $apiKey;
    private $endpointSecret;
    private $bank_name;
    private $app_name;
    private $plan_id;

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

        Stripe::setApiKey($this->apiKey);
    }

    function initByAppName($app_name)
    {
        if (!isset(Config::$apps[$app_name]['stripe'])) {
            die('Stripe config not found');
        }
        $this->app_name = $app_name;
        $this->bank_name = Config::$apps[$app_name]['stripe'];

        $bankConfig = @Config::$banks[$this->bank_name];
        $this->apiKey = $bankConfig['api_key'];
        $this->endpointSecret = $bankConfig['secret_key'];
        Stripe::setApiKey($this->apiKey);
    }

    function createPaySessionStripe($plan_alias)
    {
        $this->plan_id = Config::$banks[$this->bank_name]['product_ids'][$this->app_name][$plan_alias];
        error_log($this->plan_id . ' ' . $this->bank_name . ' ' . $plan_alias . ' ' . $this->app_name);
        // $planKey = isset($this->plans[$plan]) ? $this->plans[$plan] : '';
        if (!$this->plan_id) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'New plan and customer are required']);
            return;
        }

        try {
            // Nếu không có subscriptionId, tạo một phiên Stripe Checkout mới
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $this->plan_id,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => Config::$web_domain . "success?session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => Config::$web_domain,
            ]);
           
            header('Content-Type: application/json');
            //$session['url']
            echo json_encode(['session' => $session]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // function setLicenseKey($license_key)
    // {
    //     $this->license_key = $license_key;
    // }

    // Hàm xử lý webhook
    function handleWebhook()
    {

        $sig = @$_SERVER['HTTP_STRIPE_SIGNATURE'];
        $payload = @file_get_contents('php://input');

        if (!$sig || !$payload || !$this->endpointSecret) die("Invalid input values");

        $event = \Stripe\Webhook::constructEvent($payload, $sig, $this->endpointSecret);
       
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
                case 'invoice.payment_failed': // Khi thanh toán hóa đơn không thành công
                    $this->handleSubscriptionExpired($event->data->object);
                    break;
                    // case 'invoiceitem.created': // invoice created
                    //     $this->handleinvoiceitem($event->data->object);
                    //     break;
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
                    // error_log('Unhandled event type ' . $event->type);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
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

        $db = new DB();
        $db->setTable('licensekey');
        $itemSelectKeySend = [
            'plan_alias',
        ];
        $result = $db->selectRow($itemSelectKeySend, ['customer_id' => $customer_id]);
        $plan_alias = $result['plan_alias'];

        $insertRefund = new DB();
        $insertRefund->setTable('refund');
        $dataInsetRefund = [
            'amount_captured' => $amount_captured,
            'amount_refunded' => $amount_refunded,
            'customer_id' => $customer_id,
            'invoice_id' => $invoice_id,
            'payment_intent' => $payment_intent,
            'payment_method' => $payment_method,
            'receipt_url' => $receipt_url,
            'plan_alias' => $plan_alias,
            'created_at' => $created_date,
        ];
        $insertRefund->insertFields($dataInsetRefund);


        $udateKey = new DB();
        $udateKey->setTable('licensekey');
        $dataInsetRefund = [
            'status' => "inactive",
        ];
        $udateKey->updateFields($dataInsetRefund, ['customer_id' => $customer_id]);
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
        // $query = 'INSERT INTO `customers` (`customer_id`, `email`, `name`, `created_at`) VALUES (?, ?, ?, ?)  
        // ON DUPLICATE KEY UPDATE `email` = "' . $email . '",  `name` = "' . $name . '", `created_at` = "' . $created_date . '"';

        // $stmt = $this->getConnection()->prepare($query);
        // $stmt->execute([$customer_id, $email, $name, $created_date]);

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
            $udateinvoice = new DB();
            $udateinvoice->setTable('invoice');
            $dataInvoice = [
                'status' => $status,
                'subscription_id' => $subscription_id,
                'customer_id' => $customer_id,
                'amount_paid' => $amount_paid,
                'period_end' => $period_end
            ];
            $udateinvoice->updateFields($dataInvoice, ['invoice_id' => $invoice_id]);


            $appNameEmail = $this->getCustomerAppNameBySubscriptionId($subscription_id);

            // Chỉ cập nhật bảng licensekey nếu trạng thái là 'paid'
            if ($status == 'paid') {
                error_log('invoice update' . $appNameEmail . 'status' . $status);
                $amount_in_dollars = $amount_due / 100;
                $amount_due = number_format($amount_in_dollars, 2);
                // Gửi email thông báo
                // Create an instance of PHPMailer
                if ($appNameEmail == 'vidcombo') {

                    Common::sendSuccessEmailVidcombo($customer_email, $customer_name, $amount_due, $invoiced_date);
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

        $updateSub = new DB();
        $updateSub->setTable('invoice');
        $dataSub = [
            'status' => $status,
        ];
        $updateSub->updateFields($dataSub, ['subscription_id' => $subscription_id]);


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

    // Hàm để xử lý khi thanh toán invoice thành công
    function handleInvoicePaymentSucceeded($invoice)
    {

        // Lấy thông tin cần thiết từ invoice
        $subscription_id = $invoice->subscription;
        $customer_email = $invoice->customer_email;
        $customer_name = $invoice->customer_name;
        $invoice_id = $invoice->id;
        $status_invoice = $invoice->status;
        $subtotal_invoice = $invoice->subtotal;
        $amount_paid = $invoice->amount_paid;
        $invoice_datetime = date('Y-m-d H:i:s', $invoice->created);
        $invoiced_date = date('Y-m-d', $invoice->created);

       
        // Lấy thông tin chi tiết của subscription
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
      
        $status = $subscription->status;
        $current_period_end = $subscription->current_period_end;
        $this->plan_id = $subscription->plan->id;
        $banks_info = Config::$banks[$this->bank_name] ?? null;
        if (!$banks_info) {
            error_log("[ERROR] Bank configuration not found for: " . $this->bank_name);
            return;
        }

        list($this->app_name, $plan_alias) = Config::getAppNamePlanAliasByPlanID($this->plan_id);
        error_log("[INFO] Found Plan Alias payment success: $plan_alias, App Product: $this->app_name");

        // Chuyển đổi thời gian Unix timestamp sang định dạng ngày giờ
        $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);



        //Update lại key khi thanh toán thành công
        $dblicenseKey = new DB();
        $dblicenseKey->setTable('licensekey');
        $updateDataKey = [
            'current_period_end' => $current_period_end_date,
            'plan' => $this->plan_id,
            'plan_alias' => $plan_alias,
            'status' => 'active',
        ];
        $dblicenseKey->updateFields($updateDataKey, ['subscription_id' => $subscription_id]);


        // Cập nhật invoice trong cơ sở dữ liệu
        $db_model = new DB();
        $db_model->setTable('invoice');
        $updateData = array(
            'status' => $status_invoice,
        );
        $conditionData = array('invoice_id' => $invoice_id);
        $db_model->updateFields($updateData, $conditionData);

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn
        $dbSubcription = new DB();
        $dbSubcription->setTable('subscriptions');
        $updateDataSub = array(
            'status' => $status,
            'current_period_end' => $current_period_end_date,
        );
        $conditionDataSub = array('subscription_id' => $subscription_id);
        $stmt = $dbSubcription->updateFields($updateDataSub, $conditionDataSub);
        if (!$stmt) {
            throw new Exception('Query preparation faild ');
        }


        /*Nếu chưa gửi key -> gửi*/
        $dblicenseKey = new DB();
        $dblicenseKey->setTable('licensekey');
        $result = $dblicenseKey->selectRow('*', ['subscription_id' => $subscription_id]);
        $license_key = $result['license_key'];
        if ($license_key) {
            // $redis = new RedisCache('DETAIL_' . $license_key);
            // $redis->setCache('', 300);

            // Gửi email nếu license_key tồn tại và chưa được gửi
            if ($result['send'] == 'not') {
                error_log('send Email');
                // Lấy email khách hàng
                if ($this->app_name == 'vidcombo') {
                    $resu = Common::sendLicenseKey($customer_email, $customer_name, $license_key);
                  
                } else {
                   $resu = Common::sendLicenseKeyEmailVidobo($customer_email, $customer_name, $license_key);
                   
                }
                if($resu){
                    $dblicenseKey->updateFields(['send' => 'ok'], ['subscription_id' => $subscription_id]);
                }
               
            } else {
                error_log("No license key found for subscription ID: $subscription_id");
            }
        }

        /*Gửi email thanh toán thành công*/
        $amount_in_dollars = $amount_paid / 100;
        $amount_due = number_format($amount_in_dollars, 2);
        error_log('invoicefanalized invoice' . $this->app_name . 'status ' . $status);

        if ($this->app_name == 'vidcombo') {
            Common::sendSuccessEmailVidcombo($customer_email, $customer_name, $amount_due, $invoiced_date);
        } else {
            Common::sendSuccessEmailVidobo($customer_email, $customer_name, $amount_due, $invoiced_date);
        }
    }

    // Hàm để xử lý khi một subscription mới được tạo
    function handleSubscriptionCreated($subscription)
    {
        try {
            $subscription_id = $subscription->id;
            $status = $subscription->status;
            $current_period_start = $subscription->current_period_start;
            $current_period_end = $subscription->current_period_end;
            $customer = $subscription->customer;
            $this->plan_id = $subscription->plan->id;
            $current_period_start_date = date('Y-m-d H:i:s', $current_period_start);
            $current_period_end_date = date('Y-m-d H:i:s', $current_period_end);

            list($this->app_name, $plan_alias) = Config::getAppNamePlanAliasByPlanID($this->plan_id);
            if (!$plan_alias || !$this->app_name) {
                error_log("[ERROR] Plan alias or App Product not found for Plan ID: " . $this->plan_id);
            }
            $db = new DB();
            $db->setTable('subscriptions');
            $dataInsert = array(
                'app_name' => $this->app_name,
                'customer_id' => $customer,
                'subscription_id' => $subscription_id,
                'status' => $status,
                'current_period_start' => $current_period_start_date,
                'current_period_end' => $current_period_end_date,
                'customer' => $customer,
                'subscription_json' => json_encode($subscription),
                'plan' => $this->plan_id,
                'bank_name' => 'Stripe'
            );
            $db->insertFields($dataInsert);


            $licenseKey = $this->generateLicenseKey();

            $dbKey = new DB();
            $dbKey->setTable('licensekey');
            $dataInsertKey = array(
                'customer_id' => $customer,
                'subscription_id' => $subscription_id,
                'license_key' => $licenseKey,
                'status' => 'inactive',
                'send' => 'not',
                'plan' => $this->plan_id,
                'plan_alias' => $plan_alias ?? null,
                'sk_key' => $this->apiKey,
                'sign_key' => $this->endpointSecret,
                'created_at' => date('Y-m-d H:i:s')
            );
            $dbKey->insertFields($dataInsertKey);


            error_log("[INFO] Subscription and license key created successfully for customer: $customer");
        } catch (PDOException $e) {

            error_log("[ERROR] Failed to handle subscription creation: " . $e->getMessage());
        }
    }

    function getCustomerEmailBySubscriptionId($subscription_id)
    {
        try {
            $db = new DB();
            $db->setTable('invoice');
            $itemSelectKeySend = [
                'customer_email',
            ];
            $result = $db->selectRow($itemSelectKeySend, ['subscription_id' => $subscription_id]);

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
        $amount_due = $last_line_item['plan']['amount'];
        $created = $invoice->created;
        $period_end = $last_line_item['period']['end'];
        $period_start = $last_line_item['period']['start'];
        $subscription_id = $invoice->subscription;
        $customer_id = $invoice->customer;
        $customer_name = $invoice->customer_name;

        $invoice_date = date('Y-m-d H:i:s', $invoice->created);
        $invoiced_date = date('Y-m-d', $invoice->created);

        // Kiểm tra xem licenseKey tồn tại trong bảng licensekey
        $dbInvoice = new DB();
        $dbInvoice->setTable('invoice');
        $dataInsertInvoice = array(
            'invoice_id' => $invoice_id,
            'status' => $status,
            'amount_paid' => $amount_paid,
            'currency' => $currency,
            'customer_email' => $customer_email,
            'payment_intent' => $payment_intent,
            'amount_due' => $amount_due,
            'created' => $created,
            'period_end' => $period_end,
            'period_start' => $period_start,
            'invoice_datetime' => $invoice_date,
            'subscription_id' => $subscription_id,
            'customer_id' => $customer_id
        );
        $dbInvoice->insertFields($dataInsertInvoice);


        // Cập nhật customer_email vào bảng subscriptions


        $dbSub = new DB();
        $dbSub->setTable('subscriptions');
        $dataUpsub = array(
            'customer_email' => $customer_email,
        );
        $dbSub->updateFields($dataUpsub, ['subscription_id' => $subscription_id]);
  
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

        $dbSubcription = new DB();
        $dbSubcription->setTable('subscriptions');
        $dataKey = array(
            'status' => $status,
            'current_period_end' => $current_period_end_date,
        );
        $dbSubcription->updateFields($dataKey, ['subscription_id' => $subscription_id]);
    }

    // Hàm để xử lý khi một subscription bị xóa
    function handleSubscriptionDeleted($subscription)
    {
        // Lấy thông tin cần thiết từ subscription
        $customer = $subscription->customer;
        $subscription_id = $subscription->id;
        $status = $subscription->status;
        $period_end_subscription = date('Y-m-d H:i:s', $subscription->current_period_end);

        // Cập nhật trạng thái đăng ký trong cơ sở dữ liệu của bạn
        $dbSubcription = new DB();
        $dbSubcription->setTable('subscriptions');
        $dataKey = array(
            'status' => $status,
            'customer_id' => $customer,
            'current_period_end' => $period_end_subscription
        );
        $dbSubcription->updateFields($dataKey, ['subscription_id' => $subscription_id]);

        error_log("Subscription deleted for customer: $customer, subscription ID: $subscription_id");
    }

    function getCustomerAppNameBySubscriptionId($subscription_id)
    {
        try {

            $selectSub = new DB();
            $selectSub->setTable('subscriptions');
            $dataSub = array(
                'app_name',
            );
            $result = $selectSub->selectRow($dataSub, ['subscription_id' => $subscription_id]);

            return isset($result['app_name']) ? $result['app_name'] : null;
        } catch (PDOException $e) {
            error_log("Error occurred in getCustomerEmailBySubscriptionId: " . $e->getMessage());
            return null;
        }
    }

    function selectLicenkeytoSubcription($subscription_id)
    {
        $selectSub = new DB();
        $selectSub->setTable('licensekey');
        $result = $selectSub->selectRow('*', ['subscription_id' => $subscription_id]);

        // $license_key = isset($result['license_key']) ? $result['license_key'] : '';
        return $result;
    }

    function updateinvoice($subscription_id, $subtotal_invoice, $status_invoice)
    {


        $selectSub = new DB();
        $selectSub->setTable('invoice');
        $dataSub = array(
            'status_invoice' => $status_invoice,
            'subtotal_invoice' => $subtotal_invoice,
        );
        $selectSub->updateFields($dataSub, ['subscription_id' => $subscription_id]);
    }

    // Hàm để tạo license key ngẫu nhiên
    function generateLicenseKey()
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }

    function updateSubStripe($licenseKey, $plan, $convertname)
    {

        // Nếu có licenseKey, tìm subscriptionId và nâng cấp subscription
        $key = new Licensekey();
        $subscriptionId = $key->findSubscriptionIdByLicenseKey($licenseKey);

        if ($subscriptionId) {
            // Nếu có subscriptionId, nâng cấp subscription hiện tại
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $appNameupdateSup = $this->findAppNametoSubcritpion($subscriptionId);
            $nameBankApp = Config::$apps[$appNameupdateSup][$convertname];
            $planKey = Config::$banks[$nameBankApp]['product_ids'][$appNameupdateSup][$plan];

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
