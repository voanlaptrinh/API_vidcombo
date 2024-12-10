<?php

namespace App;
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

class Common
{

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

    public static function sendLicenseKey($customer_email, $customer_name, $licenseKey)
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
        // error_log('teamplate' . $template);
        $template = str_replace('{{ $customer_name }}', htmlspecialchars($customer_name), $template);
        $template = str_replace('{{ $licenseKey }}', htmlspecialchars($licenseKey), $template);

        $mail->Body = $template;

        // Send the email
        try {
            return $mail->send();
        } catch (Exception $e) {
            // Handle errors
            return false;
        }
    } 
    public static function sendSuccessEmailVidcombo($customer_email, $customer_name, $amount_due, $invoiced_date)
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



    //vidobo
    public static function sendLicenseKeyEmailVidobo($customer_email, $customer_name, $licenseKey)
    {
        $mail = new PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vidobo.com@gmail.com';  // Your Gmail address
        $mail->Password   = 'lwfaeqipjiwgycuu';  // Your Gmail password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;  // TCP port to connect to

        // Recipients
        $mail->setFrom('vidobo.com@gmail.com', 'Vidobo');
        $mail->addAddress($customer_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your License Key';

        // Load the HTML template
        $template = file_get_contents(__DIR__ . '/tem_mail/send_key_vidobo.html');
        // error_log('teamplate' . $template);
        $template = str_replace('{{ $customer_name }}', htmlspecialchars($customer_name), $template);
        $template = str_replace('{{ $licenseKey }}', htmlspecialchars($licenseKey), $template);

        $mail->Body = $template;

        // Send the email
        try {
            return $mail->send();
        } catch (Exception $e) {
            // Handle errors
            return false;
        }
    }   
     public static function sendSuccessEmailVidobo($customer_email, $customer_name, $amount_due, $invoiced_date)
    {

        $mail = new PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  // Use the correct SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vidobo.com@gmail.com';  // Your Gmail address
        $mail->Password   = 'lwfaeqipjiwgycuu';  // Your Gmail password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;  // TCP port to connect to

        // Recipients
        $mail->setFrom('vidobo.com@gmail.com', 'Vidobo');
        $mail->addAddress($customer_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Payment Successful';
        // Define the email body
        $template = file_get_contents(__DIR__ . '/tem_mail/send_success_vidobo.html');

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
    public static function getInt($param, $defaultValue = 0)
    {
        return isset($_GET[$param]) ? intval($_GET[$param]) : $defaultValue;
    }

    public static function getString($param, $defaultValue = "")
    {
        return isset($_GET[$param]) ? self::cleanQuery($_GET[$param]) : $defaultValue;
    }

    public static function getIntPOST($param, $defaultValue = 0)
    {
        return isset($_POST[$param]) ? intval($_POST[$param]) : $defaultValue;
    }

    public static function getStringPOST($param, $defaultValue = "")
    {
        return isset($_POST[$param]) ? self::cleanQuery($_POST[$param]) : $defaultValue;
    }

    static function cleanQuery($string)
    {
        if (empty($string)) return $string;
        $string = trim($string);

        $badWords = array(
            "/Select(.*)From/i",
            "/Union(.*)Select/i",
            "/Update(.*)Set/i",
            "/Delete(.*)From/i",
            "/Drop(.*)Table/i",
            "/Insert(.*)Into/i"
        );

        $string = preg_replace($badWords, "", $string);

        return $string;
    }

    static function getErrorMessage($lang_code, $error_key)
    {
        $lang_file = __DIR__ . '/lang/' . $lang_code . '.json';
        if (!file_exists($lang_file))
            $lang_file = __DIR__ . '/lang/en.json';

        $lang_data = json_decode(file_get_contents($lang_file), true);
        return isset($lang_data[$error_key]) ? $lang_data[$error_key] : '';
    }
   
}
