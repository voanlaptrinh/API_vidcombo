<?php
require_once './common.php';
require_once './redis.php';
$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

header("Content-Type: application/json");

//Ham lấy ra ngôn ngữ
function getErrorMessage($lang_code, $error_key) {
    $lang_file = 'lang/' . $lang_code . '.json';

    if (file_exists($lang_file)) {
        $lang_data = json_decode(file_get_contents($lang_file), true);
        if (isset($lang_data[$error_key])) {
            return $lang_data[$error_key];
        }
    }

    // Trường hợp mặc định nếu không tìm thấy thông báo
    return 'Unknown error';
}


// Lấy tham số params
$device_id = $_GET['device_id'] ?? '';
$license_key = $_GET['license_key'] ?? '';
$clientIP = Common::getRealIpAddr();
$geo = @$_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'không có thông tin quốc gia';
$os_name = $_GET['os_name'] ?? ''; // Hệ điều hành
$os_version = $_GET['os_version'] ?? ''; //Phiên bản hệ điều hành
$lang_code = $_GET['lang_code'] ?? '';
$cpu_name = $_GET['cpu_name'] ?? ''; //THông tin cpu
$cpu_arch = $_GET['cpu_arch'] ?? ''; //THông tin ram
$json_info = $_GET['json_info'] ?? ''; //THông tin ram


$today = date('Y-m-d');

if ( !$lang_code || !$device_id || !$os_name || !$os_version || !$cpu_name || !$cpu_arch || !$json_info) {
    http_response_code(400);
    $error_key = 'missing_parameters'; // Key của thông báo lỗi
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode(['error' => $error_message]);
    exit;
}


$redis = new RedisCache($license_key);
$download_count = 4; // Default download count

// Check if license key is provided
if ($license_key) {
    // Kiểm tra bộ đệm Redis để biết trạng thái khóa cấp phép
    $license_key_cache = $redis->getCache();

    if ($license_key_cache) {
        $result = json_decode($license_key_cache, true);
    } else {
        $stmt = $connection->prepare("SELECT `license_key`, `status`, `current_period_end` FROM licensekey WHERE `license_key` = :license_key");
        $stmt->execute([':license_key' => $license_key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $redis->setCache(json_encode($result), 3600); // tồn tại trong 1 giờ
        }
    }

    if ($result && $result['status'] == 'active') {
        $current_period_end = $result['current_period_end'] ? (new DateTime($result['current_period_end']))->format('d-m-Y') : 'N/A';

        // Response with premium plan info
        InsertDevice($connection, $clientIP, $geo, $device_id, $os_name, $os_version, $license_key, $today, $cpu_name, $cpu_arch,$json_info);

        echo json_encode([
            'license_key' => $license_key,
            'status' => 'active',
            'end_date' => $current_period_end,
            'error' => '',
            'plan' => 'premium',
            'download_count' => 'unlimited' // Unlimited for active license
        ]);
        exit;
    }
}


$stmt = $connection->prepare("SELECT `download_count`, `last_updated` FROM device WHERE `device_id` = :device_id");
$stmt->execute([':device_id' => $device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if ($device) {
    if ($device['last_updated'] != $today) {
        // Đặt lại số lần tải và cập nhật last_updated.
        $stmt = $connection->prepare("UPDATE device SET `download_count` = 5, `last_updated` = :today WHERE `device_id` = :device_id");
        $stmt->execute([':today' => $today, ':device_id' => $device_id]);
        $download_count = 5;
        $device['download_count'] = 5;
        $device['last_updated'] = $today;
    } elseif ($device['download_count'] > 0) {
        // Giảm số lượt tải xuống
        $stmt = $connection->prepare("UPDATE device SET `download_count` = `download_count` - 1 WHERE `device_id` = :device_id");
        $stmt->execute([':device_id' => $device_id]);
        $download_count = $device['download_count'] - 1;
        $device['download_count'] = $download_count;
    } else {
        $error_key = 'download_limit_reached'; // Key của thông báo lỗi
        $error_message = getErrorMessage($lang_code, $error_key);

        echo json_encode([
            'license_key' => $license_key,
            'status' => $result['status'] ?? 'unknown',
            'end_date' => $current_period_end ?? null,
            'error' =>  $error_message,
            'download_count' => 0,
            'plan' => 'trial',
        ]);
        exit;
    }
} else {
    // Thêm bản ghi mới cho thiết bị với số lần tải mặc định
    InsertDevice($connection, $clientIP, $geo, $device_id, $os_name, $os_version, $license_key, $today, $cpu_name, $cpu_arch,$json_info);
}

$response = [
    'license_key' => $license_key,
    'status' => $result['status'] ?? 'unknown',
    'end_date' => $current_period_end ?? null,
    'error' => '',
    'download_count' => $download_count,
    'plan' => 'trial',
];



function InsertDevice($connection, $clientIP, $geo, $device_id, $os_name, $os_version, $license_key, $today, $cpu_name, $cpu_arch,$json_info)
{
    // Kiểm tra xem đã có bản ghi với device_id và license_key này chưa
    $stmt = $connection->prepare("SELECT COUNT(*) AS count FROM device WHERE device_id = :device_id AND license_key = :license_key");
    $stmt->execute([
        ':device_id' => $device_id,
        ':license_key' => $license_key
    ]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Nếu chưa tồn tại, thực hiện insert mới
        $sql = "INSERT INTO device (client_ip, geo, device_id, os_name, os_version, license_key, download_count, last_updated, cpu_name, cpu_arch, json_info) 
                VALUES (:client_ip, :geo, :device_id, :os_name, :os_version, :license_key, 4, :today, :cpu_name, :cpu_arch, :json_info)";
        $stmt = $connection->prepare($sql);
        $stmt->execute([
            ':client_ip' => $clientIP,
            ':device_id' => $device_id,
            ':geo' => $geo,
            ':os_name' => $os_name,
            ':os_version' => $os_version,
            ':license_key' => $license_key,
            ':today' => $today,
            ':cpu_name' => $cpu_name,
            ':cpu_arch' => $cpu_arch,
            ':json_info' => $json_info,
        ]);
    }
}

echo json_encode($response);
