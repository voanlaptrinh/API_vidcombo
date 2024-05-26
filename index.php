<?php
header("Content-Type: application/json");

// Lấy địa chỉ IP của client
$clientIP = $_SERVER['REMOTE_ADDR'];

// Lấy thông tin địa lý từ IP sử dụng dịch vụ của ipinfo.io
$geoInfo = @file_get_contents("http://ipinfo.io/{$clientIP}/json");
if ($geoInfo === FALSE) {
    $geoInfo = ["error" => "Không thể lấy thông tin địa lý"];
} else {
    $geoInfo = json_decode($geoInfo, true);
}
var_dump($geoInfo);

// Lấy tên quốc gia từ thông tin địa lý
$countryName = isset($geoInfo['country']) ? $geoInfo['country'] : 'Không có thông tin quốc gia';

// Thông tin hệ điều hành của server
$osInfo = php_uname();

// Lấy tên máy chủ (hostname)
$hostname = gethostname();

// Lấy địa chỉ IP của máy chủ
if (stristr(PHP_OS, 'win')) {
    // Trên Windows
    $serverIP = gethostbyname($hostname);
} else {
    // Trên Linux/Mac
    $serverIP = shell_exec("hostname -I");
    $serverIP = trim($serverIP);
}

// Tạo response
$response = [
    'client_ip' => $clientIP,
    'geo' => $geoInfo,
    'country' => $countryName,
    'os' => $osInfo,
    'hostname' => $hostname,
    'server_ip' => $serverIP
];

// Trả về JSON response
echo json_encode($response);
?>
