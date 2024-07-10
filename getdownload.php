<?php
require_once './common.php';

$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

header("Content-Type: application/json");

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
// Lấy tham số từ request


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




if (!$lang_code || !$os_version || !$os_name || !$cpu_name ||  !$cpu_arch || !$device_id) {
    http_response_code(400);
    // Kiểm tra và xử lý thông báo lỗi
    $error_key = 'missing_parameters'; // Key của thông báo lỗi
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode(['error' => $error_message]);
    exit;
}


// Kiểm tra xem địa chỉ MAC đã tồn tại trong bảng driver hay chưa
$stmt = $connection->prepare("SELECT * FROM device WHERE device_id = :device_id");
$stmt->execute([':device_id' => $device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if ($device) {
    // Nếu đã tồn tại, kiểm tra và cập nhật số lượt tải nếu cần
    $download_count = $device['download_count'];
    $last_updated = $device['last_updated'];
    $current_date = date('Y-m-d');

    if ($last_updated != $current_date) {
        // Reset số lượt tải về 5 và cập nhật ngày hiện tại
        $download_count = 5;
        // Prepare and execute SQL statement
        $stmt = $connection->prepare("UPDATE device SET download_count = :download_count, last_updated = :current_date WHERE device_id = :device_id");
        $stmt->execute([':download_count' => $download_count, ':current_date' => $current_date, ':device_id' => $device_id]);
    }
    $error_key = 'device_information'; // Key của thông báo lỗi
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode([
        'device_id' => $device_id,
        'download_count' => $download_count,
        'message' => $error_message
    ]);
} else {
    // Nếu chưa tồn tại, thêm mới vào bảng device với số lượt tải mặc định là 5
    $default_download_count = 5;
    $current_date = date('Y-m-d');

    $sql = "INSERT INTO device (device_id, client_ip, os_name, os_version, download_count, last_updated, geo, cpu_name, cpu_arch, json_info) VALUES (:device_id, :client_ip, :os_name, :os_version, :download_count, :current_date, :geo, :cpu_name, :cpu_arch, :json_info)";
    $stmt = $connection->prepare($sql);
    $stmt->execute([
        ':client_ip' => $clientIP,
        ':device_id' => $device_id,
        ':download_count' => $default_download_count,
        ':current_date' => $current_date,
        ':os_name' => $os_name,
        ':os_version' => $os_version,
        ':geo' => $geo,
        ':cpu_name' => $cpu_name,
        ':cpu_arch' => $cpu_arch,
        ':json_info' => $json_info,
    ]);
    $error_key = 'device_successfully'; // Key của thông báo lỗi
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode([
        'device_id' => $device_id,
        'download_count' => $default_download_count,
        'message' => $error_message
    ]);
}
