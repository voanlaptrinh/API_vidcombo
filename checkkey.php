<?php
require_once 'redisCache.php';
require_once 'Common.php';
require_once 'models/DB.php';
require_once 'vendor/autoload.php';

use App\Common;
use App\Models\DB;

class checkKey
{
    public $connection;
    public $freeDL = 5;

    function check()
    {
        $license_key = Common::getString('license_key');
        $lang_code = Common::getString('lang_code', 'en');
        $device_id = urldecode(Common::getString('device_id'));
        $clientIP = Common::getRealIpAddr();
        $geo = @$_SERVER["HTTP_CF_IPCOUNTRY"] ? $_SERVER["HTTP_CF_IPCOUNTRY"] : 'unknown';
        $os_name = Common::getString('os_name'); // Operating System
        $os_version = Common::getString('os_version'); // OS Version
        $cpu_name = Common::getString('cpu_name'); // CPU Info
        $cpu_arch = Common::getString('cpu_arch'); // RAM Info
        $json_info = Common::getString('json_info'); // Additional info

        if (!$device_id || !$license_key) {
            echo json_encode(['message' => 'Handle missing parameters error']);
            exit;
        }

        $db_connector = new DB();
        $db_connector->setTable('device');
        $device_info =  $db_connector->selectAll(['*'], ['device_id' => $device_id]);

        if (!@$device_info['id']) {
            $db_connector->setTable('device');
            $dataInsert = array(
                'client_ip' => $clientIP,
                'geo' => $geo,
                'device_id' => $device_id,
                'os_name' => $os_name,
                'os_version' => $os_version,
                // 'today' => date('Y-m-d'),
                'cpu_name' => $cpu_name,
                'cpu_arch' => $cpu_arch,
                'json_info' => $json_info,
                'license_key' => $license_key,
                'download_count' => 5,
            );
            $db_connector->insertFields($dataInsert);
        } else {
            $this->freeDL = intval($device_info['download_count']);
        }

        // strlen lấy ra bao nhiêu ký tự trong chuỗi
        if (strlen($license_key) === 32) {
            $redis_license = new RedisCache('DETAIL_' . $license_key);
            $license_key_cache = $redis_license->getCache();
            if ($license_key_cache) {
                $key_row = json_decode($license_key_cache, true);
            } else {
                $db_connector->setTable('licensekey');
                $key_row =  $db_connector->selectAll(['*'], ['license_key' => $license_key]);
                $redis_license->setCache(json_encode($key_row), ($key_row ? 300 : 60)); // Cache for 5mins
            }

            if (isset($key_row['status'])) {
                $periodEndDateTime = strtotime($key_row['current_period_end']);
                $periodEndPlus15Days = strtotime('+15 days', $periodEndDateTime);
                $current_period_end = date('Y-m-d', $periodEndDateTime);
                if ($key_row['status'] == 'active') {
                    // Kiểm tra period_end trong licensekey
                    if ($periodEndPlus15Days <= time()) {
                        //Nếu bị hết hạn quá 15 ngày: Cập nhật trạng thái trong cơ sở dữ liệu thành inactive
                        $db_connector->setTable('licensekey');
                        $db_connector->updateFields(['status' => 'inactive'], ['license_key' => $license_key]);
                        $redis_license->setCache('', 60); // Cache for 1 min

                        $error_key = 'expired';
                        $status = 'inactive';
                        $this->print_mess($error_key, $lang_code, $license_key, $status, $current_period_end, $this->freeDL, true, $key_row['plan_alias']);
                    }
                    else {
                        /*Nếu hết hạn chưa quá 15 ngày:
                        * 1. Kiểm tra trạng thái sub: sub-inactive: insactive; sub-active:active
                        */
                        $error_key = 'active_key';
                        $status = $key_row['status'];
                        //Kiêm tra nếu trạng thái cuả gói sub nếu là cand thì trạng thái của key cũng đổi
                        $subscription_id = $key_row['subscription_id'];
                        $db_connector->setTable('subscriptions');
                        $rowSubscription =  $db_connector->selectAll(['status'], ['subscription_id' => $subscription_id]);
                        $statusSubscription = @$rowSubscription['status'];

                        if ($periodEndDateTime<=time() && $statusSubscription != 'active') {
                            $db_connector->setTable('licensekey');
                            $db_connector->updateFields(['status' => 'inactive'], ['license_key' => $license_key]);
                            $redis_license->setCache('', 60); // Cache for 1 min
                            $error_key = 'expired';
                            $status = 'inactive';
                            $this->print_mess($error_key, $lang_code, $license_key, $status, $current_period_end, $this->freeDL, true, $key_row['plan_alias']);
                            exit;
                        }
                        //Kết thúc kiểm tra trạng thái sub ddooir key

                        if (!isset($device_info['license_key']) || $device_info['license_key'] != $license_key)
                        {
                            $db_connector->setTable('licensekey_device');
                            $conditions = [
                                'device_id' => $device_id,
                                'license_key' => $license_key,
                            ];
                            $count_licensekey_device = $db_connector->countRecordsDistict('device_id', $conditions);

                            if (!$count_licensekey_device) {
                                $db_connector->setTable('licensekey_device');
                                $conditions = [
                                    'license_key' => $license_key,
                                ];
                                $used_device_count = $db_connector->countRecordsDistict('device_id', $conditions);
                            } else {
                                $used_device_count = 1;
                            }
                            if ($key_row['plan_alias'] == 'plan1' && $used_device_count >= 3) {
                                $error_key = 'active_limit';
                                $status = 'inactive';
                            } elseif ($key_row['plan_alias'] == 'plan2' && $used_device_count >= 5) {
                                $error_key = 'active_limit';
                                $status = 'inactive';
                            } elseif ($key_row['plan_alias'] == 'plan3' && $used_device_count >= 7) {
                                $error_key = 'active_limit';
                                $status = 'inactive';
                            } else {
                                $db_connector->setTable('device');
                                $dataUpdateDevice = [
                                    'license_key' => $license_key,
                                    'update_key_at' => date('Y-m-d H:i:s'),
                                ];
                                $db_connector->updateFields($dataUpdateDevice, ['device_id' => $device_id]);
                                
                                if (!$count_licensekey_device) {
                                    $db_connector->setTable('licensekey_device');
                                    $dataInsert = array(
                                        'device_id' => $device_id,
                                        'license_key' => $license_key,
                                    );
                                    $db_connector->insertFields($dataInsert);
                                }
                            }
                        }
                        $this->print_mess($error_key, $lang_code, $license_key, $status, $current_period_end, $this->freeDL, true, $key_row['plan_alias']);
                    }
                } 
                else 
                {
                    $error_key = 'key_inactive';
                    $this->print_mess($error_key, $lang_code, $license_key, $key_row['status'], $current_period_end, $this->freeDL, true, $key_row['plan_alias']);
                }
            } 
            else {
                $error_key = 'key_not_found';
                $this->print_mess($error_key, $lang_code, null, 'invalid', null, $this->freeDL, true, '');
            }
        } else {
            $error_key = 'key_not_found';
            $this->print_mess($error_key, $lang_code, null, 'invalid', null, $this->freeDL, true, '');
        }

        // Close database connection
        $this->connection = null;
    }

    function print_mess($error_key, $lang_code, $license, $status, $end_date, $count_free, $is_exit = false, $levers)
    {
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
        if ($is_exit) exit;
    }
}

$checkKey = new checkKey();
$checkKey->check();
