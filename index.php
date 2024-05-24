<?php
header("Content-Type: application/json");

// Lấy địa chỉ IP của client
$clientIP = $_SERVER['REMOTE_ADDR'];

// Lấy thông tin địa lý từ IP sử dụng dịch vụ của ipinfo.io
$geoInfo = @file_get_contents("http://ipinfo.io/{$clientIP}/json");
if ($geoInfo === FALSE) {
    $geoInfo = json_encode(["error" => "Không thể lấy thông tin địa lý"]);
} else {
    $geoInfo = json_decode($geoInfo, true);
}

// Thông tin hệ điều hành của server
$osInfo = php_uname();

// Tạo response
$response = [
    'ip' => $clientIP,
    'geo' => $geoInfo,
    'os' => $osInfo
];

// Trả về JSON response
echo json_encode($response);
?>
