<?php
require_once './common.php';

$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

header("Content-Type: application/json");

// Lấy tham số từ request

$clientIP = Common::getRealIpAddr();
$countryCode = @$_SERVER["HTTP_CF_IPCOUNTRY"];
if (!$countryCode) {
    $countryCode = 'không có thông tin quốc gia';
} else {
    $countryCode = @$_SERVER["HTTP_CF_IPCOUNTRY"];
}
$userAgent = $_GET['userAgent'] ?? '';
$license_key = $_GET['license_key'] ?? '';
$mac = $_GET['mac'] ?? ''; // Địa chỉ MAC
$operating = $_GET['operating'] ?? ''; // Hệ điều hành
$hostname = $_GET['hostname'] ?? ''; // Tên máy Client




if (!$mac) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing mac parameter']);
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
        $stmt = $connection->prepare("UPDATE device SET download_count = :download_count, last_updated = :current_date WHERE mac = :mac");
        $stmt->execute([':current_date' => $current_date, ':mac' => $mac]);
    } 

    echo json_encode([
        'mac' => $mac,
        'download_count' => $download_count,
        'message' => 'Driver information retrieved or updated'
    ]);
} else {
    // Nếu chưa tồn tại, thêm mới vào bảng device với số lượt tải mặc định là 5
    $default_download_count = 5;
    $current_date = date('Y-m-d');

    $sql = "INSERT INTO device (mac, download_count, last_updated, geo, os, hostname, operating) VALUES (:mac, :download_count, :current_date, :geo, :os, :hostname, :operating)";
    $stmt = $connection->prepare($sql);
    $stmt->execute([
        ':mac' => $mac,
        ':download_count' => $default_download_count,
        ':current_date' => $current_date,
        ':geo' => $countryCode,
        'os' => $userAgent,
        'hostname' => $hostname,
        'operating' => $operating,
    ]);

    echo json_encode([
        'mac' => $mac,
        'download_count' => $default_download_count,
        'message' => 'Driver added successfully'
    ]);
}
?>
