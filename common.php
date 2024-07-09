<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('UTC');
class Common
{
    static function getDatabaseConnection() {
        try {

            $connection = new PDO("mysql:host=localhost; dbname=vidcombo_db; charset=utf8;", "vidcombo_db_user", "vidcombo_db_pass");
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