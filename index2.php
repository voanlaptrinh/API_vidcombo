<?php
require_once('db.php');
header("Content-Type: application/json");

// Lấy địa chỉ IP của client
$clientIP = $_SERVER['REMOTE_ADDR'];

$geoInfo = @file_get_contents("http://ip-api.com/json/{$clientIP}?fields=status,country,countryCode");
if ($geoInfo === FALSE) {
    $geoInfo = ["error" => "Không thể lấy thông tin địa lý"];
} else {
    $geoInfo = json_decode($geoInfo, true);
}

if ($geoInfo['status'] === 'fail') {
    $countryCode = 'Không có thông tin quốc gia';
} else {
    $countryCode = $geoInfo['countryCode'];
}

// Thông tin hệ điều hành của server
$osInfo = php_uname();

// Lấy tên máy chủ
$hostname = gethostname();

if (stristr(PHP_OS, 'win')) {
    $serverIP = gethostbyname($hostname);
} else {
    $serverIP = shell_exec("hostname -I");
    $serverIP = trim($serverIP);
}

// Trích xuất tham số cpu và mac từ yêu cầu HTTP GET
$cpu = isset($_GET['cpu']) ? $_GET['cpu'] : 'Không có thông tin'; //CPU
$mac = isset($_GET['mac']) ? $_GET['mac'] : 'Không có thông tin'; //Địa chỉ mác
$operating = isset($_GET['operating']) ? $_GET['operating'] : 'Không có thông tin'; //Hệ điều hành

if (!isset($_GET['cpu']) || !isset($_GET['mac']) || !isset($_GET['operating'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}
$response = [
    'client_ip' => $clientIP,
    'geo' => $countryCode,
    'os' => $osInfo,
    'hostname' => $hostname,
    'server_ip' => $serverIP,
    'cpu' => $cpu,
    'mac' => $mac,
    'operating' => $operating
];
// Chuẩn bị truy vấn SQL
$sql = "INSERT INTO tracking (client_ip, geo, os, hostname, server_ip, cpu, mac, operating) 
        VALUES (:client_ip, :geo, :os, :hostname, :server_ip, :cpu, :mac, :operating)";

// Thực thi truy vấn
$stmt = $conn->prepare($sql);
$stmt->bindParam(':client_ip', $clientIP);
$stmt->bindParam(':geo', $countryCode);
$stmt->bindParam(':os', $osInfo);
$stmt->bindParam(':hostname', $hostname);
$stmt->bindParam(':server_ip', $serverIP);
$stmt->bindParam(':cpu', $cpu);
$stmt->bindParam(':mac', $mac);
$stmt->bindParam(':operating', $operating);

// Thực thi truy vấn
$stmt->execute();


echo json_encode($response);
