<?php
require_once './common.php';

$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

header("Content-Type: application/json");

// Lấy tham số từ request

$clientIP = Common::getRealIpAddr();
$countryCode = @$_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'không có thông tin quốc gia';
$license_key = $_GET['license_key'] ?? '';
$mac = $_GET['mac'] ?? ''; // Địa chỉ MAC
$operating = $_GET['operating'] ?? ''; // Hệ điều hành
$lang_code = $_GET['lang_code'] ?? '';
if (!$lang_code || !$mac || !$operating ) {
    http_response_code(400);
    $error_message = $lang_code === 'vi' ? 'Thông số truyền vào bị thiếu' : 'Missing required parameters';
    echo json_encode(['error' => $error_message]);
    exit;
}


// Kiểm tra xem địa chỉ MAC đã tồn tại trong bảng driver hay chưa
$stmt = $connection->prepare("SELECT * FROM device WHERE mac = :mac");
$stmt->execute([':mac' => $mac]);
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
        $stmt = $connection->prepare("UPDATE device SET download_count = :download_count, last_updated = :current_date WHERE mac = :mac");
        $stmt->execute([':download_count' => $download_count, ':current_date' => $current_date, ':mac' => $mac]);
    }

    echo json_encode([
        'mac' => $mac,
        'download_count' => $download_count,
       'message' => $lang_code === 'vi' ? 'Thông tin driver được truy xuất hoặc cập nhật' : 'Driver information retrieved or updated'
    ]);
} else {
    // Nếu chưa tồn tại, thêm mới vào bảng device với số lượt tải mặc định là 5
    $default_download_count = 5;
    $current_date = date('Y-m-d');

    $sql = "INSERT INTO device (client_ip, mac, download_count, last_updated, geo, operating) VALUES (:client_ip, :mac, :download_count, :current_date, :geo, :operating)";
    $stmt = $connection->prepare($sql);
    $stmt->execute([
        ':client_ip' => $clientIP,
        ':mac' => $mac,
        ':download_count' => $default_download_count,
        ':current_date' => $current_date,
        ':geo' => $countryCode,
        'operating' => $operating,
    ]);

    echo json_encode([
        'mac' => $mac,
        'download_count' => $default_download_count,
       'message' => $lang_code === 'vi' ? 'Driver được thêm thành công' : 'Driver added successfully'
    ]);
}

