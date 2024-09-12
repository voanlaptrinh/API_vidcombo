<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

use PHPMailer\PHPMailer\PHPMailer;

class Common
{

    // 'plan1' => 'price_1Pl0EDJykwD5LYvp7ymIxuGP', // Id test
    // // 'plan1' => 'price_1PiultJykwD5LYvpJyb57WJ9',
    // 'plan2' => 'price_1Piun4JykwD5LYvpVkpiWzuR',
    // 'plan3' => 'price_1PiunkJykwD5LYvp0IGdnFUt',
    static function getDatabaseConnection()
    {
        try {

            $connection = new PDO("mysql:host=localhost; dbname=admin_vidcombo; charset=utf8;", "root", "");
            // $connection = new PDO("mysql:host=localhost; dbname=vidcombo_db; charset=utf8;", "vidcombo_db_user", "vidcombo_db_pass");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $connection;
        } catch (PDOException $e) {
            error_log('Connection failed: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }


    static function getStripeSecrets()
    {
        $connection = self::getDatabaseConnection();

        $query = $connection->prepare("SELECT apiKey, endpointSecret, plan_jsonId FROM stripe_secrets WHERE status = 'active'");
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return [
                'apiKey' => $result['apiKey'],
                'endpointSecret' => $result['endpointSecret'],
                'plans' => json_decode($result['plan_jsonId'], true),
            ];
        }

        return null;
    }


    // public static $apiKey = 'sk_test_51OeDsPIXbeKO1uxjfGZLmBaoVYMdmbThMwRHSrNa6Zigu0FnQYuAatgfPEodv9suuRFROdNRHux5vUhDp7jC6nca00GbHqdk1Y';
    // public static $endpointSecret = 'whsec_5f17c8c4ada7dddedac39a07084388d087b1743d38e16af8bd996bb97a21c910';
    // // public static $apiKey = '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';
    // // public static $endpointSecret = 'whsec_xFaRWzhwBZ800CsllRVX89YHhxqPLja6';

    // public static $plans = array(
    //     'plan1' => 'price_1PV2QfIXbeKO1uxjVvaZPb8p', // Id test
    //     'plan2' => 'price_1PV2VjIXbeKO1uxjHlOtM0oL',
    //     'plan3' => 'price_1PV2USIXbeKO1uxjnL1w3qPC',
    // );
    static function getRealIpAddr()
    {
        if (@$_SERVER['HTTP_CLIENT_IP']) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (@$_SERVER['HTTP_CF_CONNECTING_IP']) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (@$_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = @$_SERVER['REMOTE_ADDR'];
        }
        if ($ip && strpos($ip, ',') !== false) {
            $ip = explode(',', $ip);
            $ip = @$ip[0];
        }
        return $ip;
    }
    //Gửi license key

    public static function sendLicenseKeyEmail($customer_email, $customer_name, $licenseKey)
    {
        $mail = new PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vidcombo.com@gmail.com';  // Your Gmail address
        $mail->Password   = 'fyebyrtcnehwravx';  // Your Gmail password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;  // TCP port to connect to

        // Recipients
        $mail->setFrom('vidcombo.com@gmail.com', 'Vidcombo');
        $mail->addAddress($customer_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your License Key';

        // Load the HTML template
        $template = file_get_contents(__DIR__ . '/tem_mail/send_key.html');
        error_log('teamplate' . $template);
        $template = str_replace('{{ $customer_name }}', htmlspecialchars($customer_name), $template);
        $template = str_replace('{{ $licenseKey }}', htmlspecialchars($licenseKey), $template);

        $mail->Body = $template;

        // Send the email
        try {
            $mail->send();
            return true;
        } catch (Exception $e) {
            // Handle errors
            return false;
        }
    }
    public static function sendSuccessEmail($customer_email, $customer_name, $amount_due, $invoiced_date)
    {

        $mail = new PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vidcombo.com@gmail.com';  // Your Gmail address
        $mail->Password   = 'fyebyrtcnehwravx';  // Your Gmail password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;  // TCP port to connect to

        // Recipients
        $mail->setFrom('vidcombo.com@gmail.com', 'Vidcombo');
        $mail->addAddress($customer_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Payment Successful';
        // Define the email body
        $template = file_get_contents(__DIR__ . '/tem_mail/send_success.html');

        $template = str_replace('{{ $customer_name }}', htmlspecialchars($customer_name), $template);
        $template = str_replace('{{ $amount_due }}', htmlspecialchars($amount_due), $template);
        $template = str_replace('{{ $invoiced_date }}', htmlspecialchars($invoiced_date), $template);

        // Assign the email body
        $mail->Body = $template;



        // Send the email
        try {
            $mail->send();
            return true;
        } catch (Exception $e) {
            // Handle errors
            return false;
        }
    }
}
