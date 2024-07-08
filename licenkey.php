<?php
require_once './common.php';
require_once './RedisClient.php'; //client Redí

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
$userAgent = $_GET['userAgent'] ?? '';
$license_key = $_GET['license_key'] ?? '';
$mac = $_GET['mac'] ?? ''; // Địa chỉ MAC
$operating = $_GET['operating'] ?? ''; // Hệ điều hành
$hostname = $_GET['hostname'] ?? ''; // Tên máy Client
$today = date('Y-m-d');
if (!$mac || !$operating  || !$userAgent || !$hostname) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$download_count = 4; // Default download count

// Check if license key is provided
if ($license_key) {
    $stmt = $connection->prepare("SELECT `license_key`, `status`, `current_period_end` FROM licensekey WHERE `license_key` = :license_key");
    $stmt->execute([':license_key' => $license_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['status'] == 'active') {
        $current_period_end = (new DateTime($result['current_period_end']))->format('d-m-Y');

        // Set cache (e.g., using Redis)
        // Cache logic here

        // Response with premium plan info
        InsertDevice($connection, $clientIP, $countryCode, $userAgent, $hostname, $mac, $operating, $license_key, $today);

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

// update download count for the device

$stmt = $connection->prepare("SELECT `download_count`, `last_updated` FROM device WHERE `mac` = :mac");
$stmt->execute([':mac' => $mac]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if ($device) {
    if ($device['last_updated'] != $today) {
        // Reset download count and update last_updated
        $stmt = $connection->prepare("UPDATE device SET `download_count` = 5, `last_updated` = :today WHERE `mac` = :mac");
        $stmt->execute([':today' => $today, ':mac' => $mac]);
        $download_count = 5;
    } elseif ($device['download_count'] > 0) {
        // Decrease download count
        $stmt = $connection->prepare("UPDATE device SET `download_count` = `download_count` - 1 WHERE `mac` = :mac");
        $stmt->execute([':mac' => $mac]);
        $download_count = $device['download_count'] - 1;
    } else {
        // No downloads left
        echo json_encode([
            'license_key' => $license_key,
            'status' => $result['status'] ?? 'unknown',
            'end_date' => $current_period_end ?? null,
            'error' => 'Download limit reached',
            'download_count' => 0,
            'plan' => 'trial',
        ]);
        exit;
    }
} else {
    // Insert new device record with default download count
    InsertDevice($connection, $clientIP, $countryCode, $userAgent, $hostname, $mac, $operating, $license_key, $today);
}

$response = [
    'license_key' => $license_key,
    'status' => $result['status'] ?? 'unknown',
    'end_date' => $current_period_end ?? null,
    'error' => '',
    'download_count' => $download_count,
    'plan' => 'trial',
];


function InsertDevice($connection, $clientIP, $countryCode, $userAgent, $hostname, $mac, $operating, $license_key, $today)
{
    $sql = "INSERT INTO device (client_ip, geo, os, hostname, mac, operating, license_key, download_count, last_updated) 
    VALUES (:client_ip, :geo, :os, :hostname, :mac, :operating, :license_key, 4, :today)";
    $stmt = $connection->prepare($sql);
    $stmt->execute([
        ':client_ip' => $clientIP,
        ':geo' => $countryCode,
        ':os' => $userAgent,
        ':hostname' => $hostname,
        ':mac' => $mac,
        ':operating' => $operating,
        ':license_key' => $license_key,
        ':today' => $today
    ]);
}


echo json_encode($response);
