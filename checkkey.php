<?php
require_once './redis.php';
require_once './common.php';

$license_key = $_GET['license_key']; // Key nhận từ request

if (!$license_key) {
    http_response_code(400);
    echo json_encode(['error' => 'Licenkey parameter']);
    exit;
}

$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

$redis = new RedisCache($license_key);

try {

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


    if ($result) {
        // Key tồn tại trong CSDL, xử lý logic kiểm tra trạng thái
        $status = $result['status'];
        $current_period_end = $result['current_period_end'] ? (new DateTime($result['current_period_end']))->format('d-m-Y') : 'N/A';
        // Giả sử 'status' là trường lưu trạng thái
        echo json_encode([
            'license_key' => $license_key,
            'status' => $status,
            'end_date' => $current_period_end,
        ]);
    } else {
        // Key không tồn tại
        echo json_encode(['error' => 'Key not found']);
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
$connection = null;

