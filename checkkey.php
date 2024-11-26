<?php
// require_once 'redis.php';
// require_once 'common.php';
use App\Common;
use App\Models\RedisCache;
use App\Models\DB;
class checkKey
{
    public $connection;
    public $freeDL = 5;

    function check()
    {
        $license_key = Common::getString('license_key');
        $lang_code = Common::getString('lang_code','en');
        $device_id = urldecode(Common::getString('device_id'));
        $clientIP = Common::getRealIpAddr();
        $geo = @$_SERVER["HTTP_CF_IPCOUNTRY"] ?$_SERVER["HTTP_CF_IPCOUNTRY"]: 'unknown';
        $os_name = Common::getString('os_name'); // Operating System
        $os_version = Common::getString('os_version'); // OS Version
        $cpu_name = Common::getString('cpu_name'); // CPU Info
        $cpu_arch = Common::getString('cpu_arch'); // RAM Info
        $json_info = Common::getString('json_info'); // Additional info

        if (!$device_id || !$license_key) {
            echo json_encode(['message' => 'Handle missing parameters error']);
            exit;
        }

        // $this->connection = Common::getDatabaseConnection();
        // if (!$this->connection) {
        //     throw new Exception('Database connection could not be established.');
        // }
        // Insert or update device information
        $connections = new DB();
        
        $stmt_device_check = $this->connection->prepare("SELECT `id`, `download_count`, `last_updated`, `license_key` FROM `device` WHERE `device_id` = :device_id");
        $stmt_device_check->execute([':device_id' => $device_id]);






        $device_info = $stmt_device_check->fetch(PDO::FETCH_ASSOC);

        if (!@$device_info['id']) {
            $sql_insert_device = "INSERT INTO `device` (`client_ip`,`license_key`, `geo`, `device_id`, `os_name`, `os_version`, `download_count`, `last_updated`, `cpu_name`, `cpu_arch`, `json_info`) 
                      VALUES (:client_ip, :license_key, :geo, :device_id, :os_name, :os_version, :download_count, :today, :cpu_name, :cpu_arch, :json_info)";

            $stmt_insert_device = $this->connection->prepare($sql_insert_device);
            $stmt_insert_device->execute([
                ':client_ip' => $clientIP,
                ':geo' => $geo,
                ':device_id' => $device_id,
                ':os_name' => $os_name,
                ':os_version' => $os_version,
                ':today' => date('Y-m-d'),
                ':cpu_name' => $cpu_name,
                ':cpu_arch' => $cpu_arch,
                ':json_info' => $json_info,
                ':license_key' => $license_key,
                ':download_count' => 5,
            ]);
        }
        else {
            $this->freeDL = intval($device_info['download_count']);
        }

        // strlen lấy ra bao nhiêu ký tự trong chuỗi
        if (strlen($license_key) === 32)
        {
            $redis_license = new RedisCache('DETAIL_'.$license_key);
            $license_key_cache = $redis_license->getCache();
            if ($license_key_cache) {
                $key_row = json_decode($license_key_cache, true);
            } else {
                $stmt = $this->connection->prepare("SELECT `id`,`license_key`, `status`, `current_period_end`, `plan`, `plan_alias` FROM `licensekey` WHERE `license_key` = :license_key");
                $stmt->execute([':license_key' => $license_key]);
                $key_row = $stmt->fetch(PDO::FETCH_ASSOC);

                $redis_license->setCache(json_encode($key_row), ($key_row?300:60)); // Cache for 5mins
            }

            if (isset($key_row['status']))
            {
                $periodEndDateTime = strtotime($key_row['current_period_end']);
                $current_period_end = date('Y-m-d', $periodEndDateTime);
                if($key_row['status'] == 'active')
                {
                    // Kiểm tra period_end trong licensekey
                    if ($periodEndDateTime <= time())
                    {
                        // Cập nhật trạng thái trong cơ sở dữ liệu thành inactive
                        $stmt = $this->connection->prepare("UPDATE `licensekey` SET `status` = :status WHERE `license_key` = :license_key");
                        $stmt->execute([
                            ':status' => 'inactive',
                            ':license_key' => $license_key
                        ]);
                        $redis_license->setCache('', 60); // Cache for 1 min

                        $error_key = 'expired';
                        $status = 'inactive';
                        $this->print_mess($error_key,$lang_code,$license_key, $status,$current_period_end,$this->freeDL, true, $key_row['plan_alias']);
                    }
                    else {
                        $error_key = 'active_key';
                        $status = $key_row['status'];

                        if (!isset($device_info['license_key']) || $device_info['license_key'] != $license_key) {
                            // Check if device_id and license_key combination already exists in licensekey_device
                            $stmt_check = $this->connection->prepare("SELECT COUNT(*) AS `count_licensekey_device` FROM `licensekey_device` WHERE `device_id` = :device_id AND `license_key` = :license_key");
                            $stmt_check->execute([
                                ':device_id' => $device_id,
                                ':license_key' => $license_key,
                            ]);
                            $count_licensekey_device = $stmt_check->fetchColumn();

                            if(!$count_licensekey_device){
                                $stmt_count = $this->connection->prepare("SELECT COUNT(DISTINCT `device_id`) AS `used_device_count` FROM `licensekey_device` WHERE `license_key` = :license_key");
                                $stmt_count->execute([':license_key' => $license_key]);
                                $used_device_count = $stmt_count->fetchColumn();
                            } else {
                                $used_device_count = 1;
                            }
                            if ($key_row['plan_alias'] == 'plan1' && $used_device_count >= 3) {
                                $error_key = 'active_limit';
                                $status = 'inactive';
                            }
                            elseif ($key_row['plan_alias'] == 'plan2' && $used_device_count >= 5) {
                                $error_key = 'active_limit';
                                $status = 'inactive';
                            }
                            elseif ($key_row['plan_alias'] == 'plan3' && $used_device_count >= 7) {
                                $error_key = 'active_limit';
                                $status = 'inactive';
                            }
                            else {
                                // Update the license_key in the device table
                                $sql_update_device_key = "UPDATE `device` SET `license_key` = :license_key, `update_key_at` = :cur_time WHERE `device_id` = :device_id";
                                $stmt_update_device_key = $this->connection->prepare($sql_update_device_key);
                                $stmt_update_device_key->execute([
                                    ':device_id' => $device_id,
                                    ':license_key' => $license_key,
                                    ':cur_time' => date('Y-m-d H:i:s'), // Ngày giờ hiện tại
                                ]);

                                if (!$count_licensekey_device) {
                                    // Insert into licensekey_device if not already associated
                                    $sql = "INSERT INTO `licensekey_device` (`device_id`, `license_key`) VALUES (:device_id, :license_key)";
                                    $stmt_insert = $this->connection->prepare($sql);
                                    $stmt_insert->execute([
                                        ':device_id' => $device_id,
                                        ':license_key' => $license_key,
                                    ]);
                                }
                            }
                        }
                        $this->print_mess($error_key,$lang_code,$license_key, $status,$current_period_end,$this->freeDL, true, $key_row['plan_alias']);
                    }
                }
                else {
                    $error_key = 'key_inactive';
                    $this->print_mess($error_key,$lang_code,$license_key, $key_row['status'],$current_period_end,$this->freeDL, true, $key_row['plan_alias']);
                }
            }
            else {
                $error_key = 'key_not_found';
                $this->print_mess($error_key,$lang_code,null,'invalid',null,$this->freeDL, true,'');
            }
        }
        else {
            $error_key = 'key_not_found';
            $this->print_mess($error_key,$lang_code,null,'invalid',null,$this->freeDL, true,'');
        }

        // Close database connection
        $this->connection = null;
    }

    function print_mess($error_key, $lang_code, $license, $status, $end_date, $count_free, $is_exit=false, $levers) {
        $error_message = Common::getErrorMessage($lang_code, $error_key);
        $data = array(
            'license_key' => $license,
            'mess' => $error_message,
            'status' => $status,
            'end_date' => $end_date,
            'count_free' => $count_free,
            'lever' => $levers,
        );
        echo json_encode($data);
        if($is_exit) exit;
    }
}

$checkKey = new checkKey();
$checkKey->check();