<?php
class Common
{
    static function getDatabaseConnection() {
        try {

            $pdo = new PDO("mysql:host=localhost; dbname=admin_vidcombo; charset=utf8;", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            error_log('Connection failed: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }
}