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
$device_id = isset($_GET['device_id']) ? urldecode($_GET['device_id']) : '';
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
if (!$device_id || !$os_name || !$os_version || !$cpu_name || !$cpu_arch || !$json_info) {
    http_response_code(400);
    echo json_encode(['message' => 'Handle missing parameters error']);
    exit;
}

// Insert or update device information
$stmt_device_check = $connection->prepare("SELECT COUNT(*) AS count, download_count, last_updated, license_key FROM device WHERE device_id = :device_id");
$stmt_device_check->execute([':device_id' => $device_id]);
$device_info = $stmt_device_check->fetch(PDO::FETCH_ASSOC);


if ($device_info['count'] == 0) {

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
        ':download_count' => 10,
    ]);
} else {

    if ($device_info['license_key'] != $license_key) {
        // Update the license_key in the device table
        $sql_update_device_key = "UPDATE device SET license_key = :license_key WHERE device_id = :device_id";
        $stmt_update_device_key = $connection->prepare($sql_update_device_key);
        $stmt_update_device_key->execute([
            ':license_key' => $license_key,
            ':device_id' => $device_id,
        ]);
    }
    // if (!isset($device_info['last_updated']) || $today !== date('Y-m-d', strtotime($device_info['last_updated']))) {
    //     // Fetch current download_count and last_updated
    //     $stmt = $connection->prepare("SELECT `download_count`, `last_updated` FROM device WHERE `device_id` = :device_id");
    //     $stmt->execute([':device_id' => $device_id]);
    //     $device = $stmt->fetch(PDO::FETCH_ASSOC);

    //     // Update device table with download_count = 3 and today's date
    //     $sql_update_device = "UPDATE device SET download_count =10, last_updated = :today WHERE device_id = :device_id";
    //     $stmt_update_device = $connection->prepare($sql_update_device);

    //     $stmt_update_device->execute([
    //         ':today' => $today,
    //         ':device_id' => $device_id,

    //     ]);


    //     $device_info['download_count'] = 3;
    //     $device_info['last_updated'] = $today;
    // }
}



// strlen lấy ra bao nhiêu ký tự trong chuỗi
if ($license_key && strlen($license_key) <= 32 ) {
   
    $redis = new RedisCache($license_key);
    $license_key_cache = $redis->getCache();

    if ($license_key_cache) {
        $key_result = json_decode($license_key_cache, true);
    } else {
        $stmt = $connection->prepare("SELECT `license_key`, `status`, `current_period_end`, `plan`, `plan_alias` FROM licensekey WHERE `license_key` = :license_key");
        $stmt->execute([':license_key' => $license_key]);
        $key_result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($key_result) {
            $redis->delCache(); // Xóa cache cũ
            $redis->setCache(json_encode($key_result), 3600); // Cache for 1 hour
        }
    }

    if ($key_result && $key_result['status'] == 'active') {

        // If license key is active, insert into licensekey_device table
        $status = $key_result['status'];
        $current_period_end = $key_result['current_period_end'] ? (new DateTime($key_result['current_period_end']))->format('d/m/Y') : 'N/A';

        // Check if device_id and license_key combination already exists in licensekey_device
        $stmt_check = $connection->prepare("SELECT COUNT(*) AS count FROM licensekey_device WHERE device_id = :device_id AND license_key = :license_key");
        $stmt_check->execute([
            ':device_id' => $device_id,
            ':license_key' => $license_key,
        ]);
        $count = $stmt_check->fetchColumn();
        // Kiểm tra period_end trong licensekey
        $currentDateTime = new DateTime();
        $periodEndDateTime = new DateTime($key_result['current_period_end']);


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


    $stmt_count = $connection->prepare("SELECT COUNT(DISTINCT device_id) AS used_device_count FROM licensekey_device WHERE license_key = :license_key");
    $stmt_count->execute([':license_key' => $license_key]);
    $used_device_count = $stmt_count->fetchColumn();

    if ($key_result && is_array($key_result) && $key_result['status'] == 'active') {
        $error_key = 'active_key';
        $error_message = getErrorMessage($lang_code, $error_key);

        if ($key_result['plan_alias'] == 'plan1' && $used_device_count > 3) {
            $error_key = 'active_limit';
            $error_message = getErrorMessage($lang_code, $error_key);
            $status = 'inactive';
        }
        if ($key_result['plan_alias'] == 'plan2' && $used_device_count > 5) {
            $error_key = 'active_limit';
            $error_message = getErrorMessage($lang_code, $error_key);
            $status = 'inactive';
        }
        if ($key_result['plan_alias'] == 'plan3' && $used_device_count > 7) {
            $error_key = 'active_limit';
            $error_message = getErrorMessage($lang_code, $error_key);
            $status = 'inactive';
        }

        echo json_encode([
            'license_key' => $license_key,
            'status' => $status,
            'end_date' => $current_period_end,
            'count_free' => ($device_info['count'] == 0) ? 10 : $device_info['download_count'],
            'used_device_count' => $used_device_count,
            // 'plan' => $key_result['plan'],
            'lever' => $key_result['plan_alias'],
            'mess' => $error_message,
        ]);
    } elseif ($key_result && is_array($key_result) && $key_result['status'] == 'inactive') {
        $error_key = 'key_inactive';
        $error_message = getErrorMessage($lang_code, $error_key);
        $current_period_end = $key_result['current_period_end'] ? (new DateTime($key_result['current_period_end']))->format('d/m/Y') : 'N/A';
        echo json_encode([
            'license_key' => $license_key,
            'status' => $key_result['status'],
            'end_date' =>  $current_period_end,
            'count_free' => ($device_info['count'] == 0) ? 10 : $device_info['download_count'],
            'mess' => $error_message,
        ]);
    }
    else {
        $error_key = 'key_not_found';
        $error_message = getErrorMessage($lang_code, $error_key);
        echo json_encode([
            'license_key' => null,
            'mess' => $error_message,
            'status' => 'invalid',
            'end_date' => null,
            'count_free' => ($device_info['count'] == 0) ? 10 : $device_info['download_count'],
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
        'count_free' => ($device_info['count'] == 0) ? 10 : $device_info['download_count'],
    ]);
}




// Close database connection
$connection = null;
