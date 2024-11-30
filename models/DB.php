<?php

namespace App\Models;

use Exception;
use PDO;
use PDOException;

class DB
{
    private $connection;
    public $table;

    /**
     * @param mixed $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }
    function __construct()
    {
        $this->connectDB();
    }
    function connectDB()
    {
        try {
            $this->connection = new PDO("mysql:host=localhost; dbname=admin_vidcombo; charset=utf8;", "root", "");
            // $connection = new PDO("mysql:host=localhost; dbname=vidcombo_db; charset=utf8;", "vidcombo_db_user", "vidcombo_db_pass");
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Connection failed: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }
    public function getConnection()
    {
        if (!$this->connection)
            $this->connectDB();
        else
            return $this->connection;

        if (!$this->connection) {
            throw new Exception('Database connection could not be established.');
        }
        return $this->connection;
    }
    public function selectAll($fields = [], $conditionDatas = [])
    {
        $execData = array();
        $conditionStr = '';
        if (!empty($conditionDatas)) {
            $conditions = [];
            foreach ($conditionDatas as $condField => $condData) {
                $conditions[] = '`' . $condField . '` = :' . $condField;
                $execData[':' . $condField] = $condData;
            }
            $conditionStr = 'WHERE ' . implode(' AND ', $conditions);
        }
    
        if (empty($fields)) {
            $fields = '*';
        } else {
            $fields = implode(',', $fields);
        }
    
        $stmt = $this->getConnection()->prepare("SELECT $fields FROM `" . $this->table . "` $conditionStr");
        $stmt->execute($execData);
    
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function selectRow($fields, $conditionDatas)
    {
        $execData = array();
        $conditionStr = array();
        foreach ($conditionDatas as $condField => $condData) {
            $conditionStr[] = '`' . $condField . '` = :' . $condField . '';
            $execData[':' . $condField] = $condData;
        }
        $conditionStr = implode(' AND ', $conditionStr);
        $fields = is_array($fields)?implode(',', $fields) : $fields;

        $stmt = $this->getConnection()->prepare("SELECT $fields FROM `" . $this->table . "` WHERE " . $conditionStr);
        $stmt->execute($execData);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    function updateFields($setDatas, $conditionDatas)
    {
        $execData = array();
        $setStr = array();
        foreach ($setDatas as $setField => $setData) {
            $setStr[] = '`' . $setField . '` = :' . $setField . '';
            $execData[':' . $setField] = $setData;
        }
        $setStr = implode(', ', $setStr);

        $conditionStr = array();
        foreach ($conditionDatas as $condField => $condData) {
            $conditionStr[] = '`' . $condField . '` = :' . $condField . '';
            $execData[':' . $condField] = $condData;
        }
        $conditionStr = implode(' AND ', $conditionStr);

        $stmt = $this->getConnection()->prepare("UPDATE `" . $this->table . "` SET $setStr WHERE " . $conditionStr);
        if (!$stmt) {
            throw new Exception('Query preparation failed');
        }
        return $stmt->execute($execData);
    }


    function insertFields($setDatas)
    {
        $execData = array();
        $columns = array();
        $placeholders = array();
        foreach ($setDatas as $setField => $setData) {
            $columns[] = '`' . $setField . '`';
            $placeholders[] = ':' . $setField;
            $execData[':' . $setField] = $setData;
        }

        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);
        $stmt = $this->getConnection()->prepare("INSERT INTO `" . $this->table . "` ($columnsStr) VALUES ($placeholdersStr)");

        if (!$stmt) {
            throw new Exception('Query preparation failed');
        }
        return $stmt->execute($execData);
    }

    function countRecords($conditions = [])
    {
        $execData = [];
        $whereClause = '';
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`$field` = :$field";
                $execData[":$field"] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }

        $query = "SELECT COUNT(*) AS count FROM `" . $this->table . "` $whereClause";
        $stmt = $this->getConnection()->prepare($query);
    
        if (!$stmt) {
            throw new Exception('Query preparation failed');
        }
        $stmt->execute($execData);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
        return $result['count'];
    }
    




    function getStripeSecrets($appName = null, $name = null)
    {
        // $redis = new RedisCache('stripe_secrets');
        // $cache = $redis->getCache();
        // if ($cache) {
        // $result = json_decode($cache, true);
        // } else {
        if ($appName != null) {
            $query = $this->connection->prepare("SELECT `apiKey`, `endpointSecret`, `plan_jsonId`, `app_name`
                                             FROM `stripe_secrets` 
                                            WHERE `status` = :status AND `app_name` = :app_name");
            $query->execute([
                ':status' => 'active',
                ':app_name' => $appName
            ]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }

        if ($name != null) {
            $query = $this->connection->prepare("SELECT `apiKey`, `endpointSecret`, `plan_jsonId`, `app_name`
                                             FROM `stripe_secrets` 
                                            WHERE `status` = :status AND `name` = :name");
            $query->execute([
                ':status' => 'active',
                ':name' => $name
            ]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }
        //     $redis->setCache(json_encode($result), 3600*24); // Cache for 1day
        // }

        if ($result) {
            return [
                'apiKey' => $result['apiKey'],
                'endpointSecret' => $result['endpointSecret'],
                'app_name' => $result['app_name'],
                'plans' => json_decode($result['plan_jsonId'], true),
            ];
        }

        return null;
    }
    function getPaypalSecrets($appName = null, $name = null)
    {
        // Uncomment and configure RedisCache if needed
        // $redis = new RedisCache('stripe_secrets');
        // $cache = $redis->getCache();
        // if ($cache) {
        //     $result = json_decode($cache, true);
        // } else {
        if ($appName != null) {
            $query = $this->connection->prepare("SELECT `client_id`, `webhook_id`, `client_secret`, `plan_jsonId` , `app_name`
                                            FROM `paypal_secrets` 
                                            WHERE `status` = :status AND `app_name` = :app_name");
            $query->execute([
                ':status' => 'active',
                ':app_name' => $appName
            ]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }

        if ($name != null) {
            $query = $this->connection->prepare("SELECT `client_id`, `webhook_id`, `client_secret`, `plan_jsonId` , `app_name`
                                            FROM `paypal_secrets` 
                                            WHERE `status` = :status AND `name` = :name");
            $query->execute([
                ':status' => 'active',
                ':name' => $name
            ]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }

        // Uncomment and configure caching if needed
        // $redis->setCache(json_encode($result), 3600 * 24); // Cache for 1 day
        // }

        if ($result) {
            return [
                'client_id' => $result['client_id'],
                'client_secret' => $result['client_secret'],
                'webhook_id' => $result['webhook_id'],
                'app_name' => $result['app_name'],
                'plans' => json_decode($result['plan_jsonId'], true),
            ];
        }

        return null;
    }
    function findAppNameBySubID($subscriptionId)
    {

        // Chuẩn bị và thực hiện truy vấn
        $stmt = $this->getConnection()->prepare("SELECT `app_name` FROM `subscriptions` WHERE `subscription_id` = :subscription_id");
        $stmt->execute([':subscription_id' => $subscriptionId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (isset($device['app_name']) && $device['app_name'])
            return $device['app_name'];
        return null;
    }
    function getBankNameByLicenseKey($licenseKey)
    {
        try {
            // First query to get the subscription_id from the licensekey table
            $stmt =  $this->getConnection()->prepare("SELECT subscription_id FROM licensekey WHERE license_key = :licenseKey");
            $stmt->execute([':licenseKey' => $licenseKey]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if a subscription_id was found
            if ($result && isset($result['subscription_id'])) {
                $subscriptionId = $result['subscription_id'];

                // Second query to get the back_name from the subscriptions table
                $stmt =  $this->getConnection()->prepare("SELECT bank_name FROM subscriptions WHERE subscription_id = :subscriptionId");
                $stmt->execute([':subscriptionId' => $subscriptionId]);
                $subscriptionResult = $stmt->fetch(PDO::FETCH_ASSOC);

                // Return the bank_name if found
                if ($subscriptionResult && isset($subscriptionResult['bank_name'])) {
                    return $subscriptionResult['bank_name'];
                } else {
                    return "bank_name not found for the given subscription_id.";
                }
            } else {
                return "subscription_id not found for the given license_key.";
            }
        } catch (PDOException $e) {
            return "Database error: " . $e->getMessage();
        }
    }
    public function countRecordsDistict($distinctField, $conditions = [])
    {
        $execData = [];
        $whereClause = '';
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`$field` = :$field";
                $execData[":$field"] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }
    
        if (empty($distinctField)) {
            throw new Exception('Distinct field must be provided');
        }
    
        $selectPart = "COUNT(DISTINCT `$distinctField`) AS count";
    
        $query = "SELECT $selectPart FROM `" . $this->table . "` $whereClause";
        $stmt = $this->getConnection()->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Query preparation failed');
        }
    
        $stmt->execute($execData);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
        return $result['count'];
    }
    
}
