<?php
require_once './redis.php';
require_once './common.php';

//Hầm lấy ra ngôn ngữ
function getErrorMessage($lang_code, $error_key)
{
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


$license_key = $_GET['license_key'] ?? ''; // Key nhận từ request
$lang_code = $_GET['lang_code'] ?? '';



if (!$license_key || !$lang_code) {
    http_response_code(400);
    $error_key = 'key_lang_missing'; // Key của thông báo lỗi
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode(['error' => $error_message]);
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
        $error_key = 'key_not_found'; // Key của thông báo lỗi
        $error_message = getErrorMessage($lang_code, $error_key);

        echo json_encode(['error' => $error_message]);
    }
} catch (PDOException $e) {
    if ($lang_code === 'vi') {
        echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
    } else {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
}
$connection = null;
