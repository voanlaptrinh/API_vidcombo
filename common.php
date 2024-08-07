<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('UTC');
class Common
{
    public static $apiKey = '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';
    public static $endpointSecret = 'whsec_xFaRWzhwBZ800CsllRVX89YHhxqPLja6';

    public static $plans = array(
        'plan1' => 'price_1Pl0EDJykwD5LYvp7ymIxuGP', // Id test
        // 'plan1' => 'price_1PiultJykwD5LYvpJyb57WJ9',
        'plan2' => 'price_1Piun4JykwD5LYvpVkpiWzuR',
        'plan3' => 'price_1PiunkJykwD5LYvp0IGdnFUt',
    );

    static function getDatabaseConnection() {
        try {

            $connection = new PDO("mysql:host=localhost; dbname=admin_vidcombo; charset=utf8;", "root", "");
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $connection;
        } catch (PDOException $e) {
            error_log('Connection failed: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }

    static function getRealIpAddr()
    {
        if (@$_SERVER['HTTP_CLIENT_IP'])
        {
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (@$_SERVER['HTTP_CF_CONNECTING_IP'])
        {
            $ip=$_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        elseif (@$_SERVER['HTTP_X_FORWARDED_FOR'])
        {
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $ip=@$_SERVER['REMOTE_ADDR'];
        }
        if($ip && strpos($ip,',')!==false){
            $ip = explode(',', $ip);
            $ip = @$ip[0];
        }
        return $ip;
    }

}