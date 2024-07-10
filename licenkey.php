<?php
require_once './common.php';
require_once './redis.php';
$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

header("Content-Type: application/json");


// Lấy tham số params
$clientIP = Common::getRealIpAddr();
$countryCode = @$_SERVER["HTTP_CF_IPCOUNTRY"];
if (!$countryCode) {
    $countryCode = 'không có thông tin quốc gia';
} else {
    $countryCode = @$_SERVER["HTTP_CF_IPCOUNTRY"];
}

$license_key = $_GET['license_key'] ?? '';
$mac = $_GET['mac'] ?? ''; // Địa chỉ MAC
$operating = $_GET['operating'] ?? ''; // Hệ điều hành
$lang_code = $_GET['lang_code'] ?? '';
$cpu = $_GET['cpu'] ?? ''; //THông tin cpu
$ram = $_GET['ram'] ?? ''; //THông tin ram

$today = date('Y-m-d');
if (!$mac || !$operating || !$lang_code || !$cpu || !$ram) {
    http_response_code(400);
    $error_message = $lang_code === 'vi' ? 'Thông số truyền vào bị thiếu' : 'Missing required parameters';
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
        InsertDevice($connection, $clientIP, $countryCode, $mac, $operating, $license_key, $today, $ram, $cpu);

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


$stmt = $connection->prepare("SELECT `download_count`, `last_updated` FROM device WHERE `mac` = :mac");
$stmt->execute([':mac' => $mac]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if ($device) {
    if ($device['last_updated'] != $today) {
        // Đặt lại số lần tải và cập nhật last_updated.
        $stmt = $connection->prepare("UPDATE device SET `download_count` = 5, `last_updated` = :today WHERE `mac` = :mac");
        $stmt->execute([':today' => $today, ':mac' => $mac]);
        $download_count = 5;
        $device['download_count'] = 5;
        $device['last_updated'] = $today;
    } elseif ($device['download_count'] > 0) {
        // Giảm số lượt tải xuống
        $stmt = $connection->prepare("UPDATE device SET `download_count` = `download_count` - 1 WHERE `mac` = :mac");
        $stmt->execute([':mac' => $mac]);
        $download_count = $device['download_count'] - 1;
        $device['download_count'] = $download_count;
    } else {
        // Không còn lượt tải về
        echo json_encode([
            'license_key' => $license_key,
            'status' => $result['status'] ?? 'unknown',
            'end_date' => $current_period_end ?? null,
            'error' =>  $lang_code === 'vi' ? 'Đã đạt đến giới hạn tải xuống' : 'Download limit reached',
            'download_count' => 0,
            'plan' => 'trial',
        ]);
        exit;
    }
} else {
    // Thêm bản ghi mới cho thiết bị với số lần tải mặc định
    InsertDevice($connection, $clientIP, $countryCode, $mac, $operating, $license_key, $today, $ram, $cpu);
}

$response = [
    'license_key' => $license_key,
    'status' => $result['status'] ?? 'unknown',
    'end_date' => $current_period_end ?? null,
    'error' => '',
    'download_count' => $download_count,
    'plan' => 'trial',
];



function InsertDevice($connection, $clientIP, $countryCode, $mac, $operating, $license_key, $today, $ram, $cpu)
{
    // Kiểm tra xem đã có bản ghi với mac và license_key này chưa
    $stmt = $connection->prepare("SELECT COUNT(*) AS count FROM device WHERE mac = :mac AND license_key = :license_key");
    $stmt->execute([
        ':mac' => $mac,
        ':license_key' => $license_key
    ]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Nếu chưa tồn tại, thực hiện insert mới
        $sql = "INSERT INTO device (client_ip, geo, mac, operating, license_key, download_count, last_updated, ram, cpu) 
                VALUES (:client_ip, :geo, :mac, :operating, :license_key, 4, :today, :ram, :cpu)";
        $stmt = $connection->prepare($sql);
        $stmt->execute([
            ':client_ip' => $clientIP,
            ':geo' => $countryCode,
            ':mac' => $mac,
            ':operating' => $operating,
            ':license_key' => $license_key,
            ':today' => $today,
            ':ram' => $ram,
            ':cpu' => $cpu,
        ]);
    }
}

echo json_encode($response);

