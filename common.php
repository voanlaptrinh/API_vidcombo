<?php

// set time_zone_default
class Common
{
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
}