<?php
require_once './redis.php';
require_once './common.php';

// Function to get error message based on language
function getErrorMessage($lang_code, $error_key)
{
    $lang_file = 'lang/' . $lang_code . '.json';

    if (file_exists($lang_file)) {
        $lang_data = json_decode(file_get_contents($lang_file), true);
        if (isset($lang_data[$error_key])) {
            return $lang_data[$error_key];
        }
    }

    // Default message if error key not found
    return 'Unknown error';
}

$today = date('Y-m-d');

$license_key = $_GET['license_key'] ?? ''; // Key from request
$lang_code = $_GET['lang_code'] ?? '';
$device_id = urldecode($_GET['device_id']) ?? '';
$clientIP = Common::getRealIpAddr();
$geo = @$_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'Không có thông tin quốc gia';
$os_name = $_GET['os_name'] ?? ''; // Operating System
$os_version = $_GET['os_version'] ?? ''; // OS Version
$cpu_name = $_GET['cpu_name'] ?? ''; // CPU Info
$cpu_arch = $_GET['cpu_arch'] ?? ''; // RAM Info
$json_info = $_GET['json_info'] ?? ''; // Additional info

$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

try {

    // Check Redis cache to determine license key status
    $redis = new RedisCache($license_key);
    $license_key_cache = $redis->getCache();

    if ($license_key_cache) {
        $result = json_decode($license_key_cache, true);
    } else {
        $stmt = $connection->prepare("SELECT `license_key`, `status`, `current_period_end`, `plan` FROM licensekey WHERE `license_key` = :license_key");
        $stmt->execute([':license_key' => $license_key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $redis->delCache(); // Xóa cache cũ
            $redis->setCache(json_encode($result), 3600); // Cache for 1 hour
        }
    }

    if ($result && $result['status'] == 'active') {

        // If license key is active, insert into licensekey_device table
        $status = $result['status'];
        $current_period_end = $result['current_period_end'] ? (new DateTime($result['current_period_end']))->format('d/m/Y') : 'N/A';

        // Check if device_id and license_key combination already exists in licensekey_device
        $stmt_check = $connection->prepare("SELECT COUNT(*) AS count FROM licensekey_device WHERE device_id = :device_id AND license_key = :license_key");
        $stmt_check->execute([
            ':device_id' => $device_id,
            ':license_key' => $license_key,
        ]);
        $count = $stmt_check->fetchColumn();
        // Kiểm tra period_end trong licensekey
        $currentDateTime = new DateTime();
        $periodEndDateTime = new DateTime($result['current_period_end']);


        if ($periodEndDateTime <= $currentDateTime) {
            $status = 'inactive';
            $error_key = 'expired';
            $error_message = getErrorMessage($lang_code, $error_key);

            // Cập nhật trạng thái trong cơ sở dữ liệu thành inactive
            $stmt = $connection->prepare("UPDATE licensekey SET status = :status WHERE license_key = :license_key");
            $stmt->execute([
                ':status' => 'inactive',
                ':license_key' => $license_key
            ]);
        }
        if ($count == 0) {
            // Insert into licensekey_device if not already associated
            $sql = "INSERT INTO licensekey_device (device_id, license_key) VALUES (:device_id, :license_key)";
            $stmt_insert = $connection->prepare($sql);
            $stmt_insert->execute([
                ':device_id' => $device_id,
                ':license_key' => $license_key,
            ]);
        }


    }

    // Insert or update device information
    $stmt_device_check = $connection->prepare("SELECT COUNT(*) AS count, download_count, last_updated FROM device WHERE device_id = :device_id");
    $stmt_device_check->execute([':device_id' => $device_id]);
    $device_info = $stmt_device_check->fetch(PDO::FETCH_ASSOC);

    if ($device_info['count'] == 0) {
        // Insert new device record with download_count = 5
        $sql_insert_device = "INSERT INTO device (client_ip,license_key, geo, device_id, os_name, os_version, download_count, last_updated, cpu_name, cpu_arch, json_info) 
                          VALUES (:client_ip, :license_key, :geo, :device_id, :os_name, :os_version, :download_count, :today, :cpu_name, :cpu_arch, :json_info)";
        
        $stmt_insert_device = $connection->prepare($sql_insert_device);
        $stmt_insert_device->execute([
            ':client_ip' => $clientIP,
            ':geo' => $geo,
            ':device_id' => $device_id,
            ':os_name' => $os_name,
            ':os_version' => $os_version,
            ':today' => $today,
            ':cpu_name' => $cpu_name,
            ':cpu_arch' => $cpu_arch,
            ':json_info' => $json_info,
            ':license_key' => $license_key,
            ':download_count' => 5,
        ]);
    } else {

        if (!isset($device_info['last_updated']) || $today !== date('Y-m-d', strtotime($device_info['last_updated']))) {
            // Fetch current download_count and last_updated
            $stmt = $connection->prepare("SELECT `download_count`, `last_updated` FROM device WHERE `device_id` = :device_id");
            $stmt->execute([':device_id' => $device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update device table with download_count = 5 and today's date
            $sql_update_device = "UPDATE device SET download_count = 5, last_updated = :today WHERE device_id = :device_id";
            $stmt_update_device = $connection->prepare($sql_update_device);
            $stmt_update_device->execute([
                ':today' => $today,
                ':device_id' => $device_id,
            ]);

            $download_count = 5;
            $device_info['download_count'] = 5;
            $device_info['last_updated'] = $today;
        }
    }
    
    if ($result) {
        $stmt_count = $connection->prepare("SELECT COUNT(DISTINCT device_id) AS used_device_count FROM licensekey_device WHERE license_key = :license_key");
        $stmt_count->execute([':license_key' => $license_key]);
        $used_device_count = $stmt_count->fetchColumn();

        if ($result['status'] == 'active') {
            $error_key = 'active_key';
            $error_message = getErrorMessage($lang_code, $error_key);

            $plan1 = 'price_1PiultJykwD5LYvpJyb57WJ9';
            $plan2 = 'price_1Piun4JykwD5LYvpVkpiWzuR';
            $plan3 = 'price_1PiunkJykwD5LYvp0IGdnFUt';

            if ($result['plan'] == $plan1 && $used_device_count > 5) {
                $error_key = 'active_limit';
                $error_message = getErrorMessage($lang_code, $error_key);
                $status = 'inactive';
            }
            if ($result['plan'] == $plan2 && $used_device_count > 7) {
                $error_key = 'active_limit';
                $error_message = getErrorMessage($lang_code, $error_key);
                $status = 'inactive';
            }
            if ($result['plan'] == $plan3 && $used_device_count > 10) {
                $error_key = 'active_limit';
                $error_message = getErrorMessage($lang_code, $error_key);
                $status = 'inactive';
            }
            if ($result['plan'] == $plan1) {
                $lever = '1';
            } elseif ($result['plan'] == $plan2) {
                $lever = '2';
            } else {
                $lever = '3';
            }

            echo json_encode([
                'license_key' => $license_key,
                'status' => $status,
                'end_date' => $current_period_end,
                'count_free' => ($device_info['count'] == 0) ? 5 : $device_info['download_count'],
                'used_device_count' => $used_device_count,
                'plan' => $result['plan'],
                'lever' => $lever,
                'mess' => $error_message,
            ]);
        } elseif ($result['status'] == 'inactive') {
            $error_key = 'key_inactive';
            $error_message = getErrorMessage($lang_code, $error_key);
            $current_period_end = $result['current_period_end'] ? (new DateTime($result['current_period_end']))->format('d/m/Y') : 'N/A';
            echo json_encode([
                'license_key' => $license_key,
                'status' => $result['status'],
                'end_date' =>  $current_period_end,
                'count_free' => ($device_info['count'] == 0) ? 5 : $device_info['download_count'],
                'mess' => $error_message,
            ]);
        }
    } else {
        $error_key = 'key_not_found';
        $error_message = getErrorMessage($lang_code, $error_key);

        echo json_encode([
            'license_key' => null,
            'mess' => $error_message,
            'status' => 'invalid',
            'end_date' => null,
            'count_free' => ($device_info['count'] == 0) ? 5 : $device_info['download_count'],
        ]);
    }
} catch (PDOException $e) {
    if ($lang_code === 'vi') {
        echo json_encode(['mess' => 'Lỗi: ' . $e->getMessage()]);
    } else {
        echo json_encode(['mess' => 'Error: ' . $e->getMessage()]);
    }
}

// Close database connection
$connection = null;
