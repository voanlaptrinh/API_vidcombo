<?php
require_once './common.php';

$connection = Common::getDatabaseConnection();
if (!$connection) {
    throw new Exception('Database connection could not be established.');
}

header("Content-Type: application/json");

// Function to retrieve error messages from language file
function getErrorMessage($lang_code, $error_key)
{
    $lang_file = 'lang/' . $lang_code . '.json';

    if (file_exists($lang_file)) {
        $lang_data = json_decode(file_get_contents($lang_file), true);
        if (isset($lang_data[$error_key])) {
            return $lang_data[$error_key];
        }
    }

    // Default error message if key not found
    return 'Unknown error';
}

// Retrieve parameters from request
$device_id = $_GET['device_id'] ?? '';
$count = $_GET['count'] ?? '';
$lang_code = $_GET['lang_code'] ?? '';

if (!$lang_code || !$count || !$device_id) {
    http_response_code(400);
    // Handle missing parameters error
    $error_key = 'missing_parameters';
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode(['error' => $error_message]);
    exit;
}

// Check if the device ID exists in the database
$stmt = $connection->prepare("SELECT * FROM device WHERE device_id = :device_id");
$stmt->execute([':device_id' => $device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if ($device) {
    try {
        // Calculate new download count
        $download_count = $device['download_count'];
        $new_download_count = max($download_count - $count, 0); // Ensure download count doesn't go negative

        // Update the device record with the new download count
        $update_stmt = $connection->prepare("UPDATE device SET download_count = :new_download_count WHERE device_id = :device_id");
        $update_stmt->execute([
            ':new_download_count' => $new_download_count,
            ':device_id' => $device_id
        ]);

        // Retrieve updated device information
        $updated_device_stmt = $connection->prepare("SELECT * FROM device WHERE device_id = :device_id");
        $updated_device_stmt->execute([':device_id' => $device_id]);
        $updated_device = $updated_device_stmt->fetch(PDO::FETCH_ASSOC);

        // Prepare success response
        $error_key = 'device_information';
        $error_message = getErrorMessage($lang_code, $error_key);
        echo json_encode([
            'device_id' => $device_id,
            'count' => $updated_device['download_count'],
            'isAccept' => true,
            'message' => $error_message
        ]);
    } catch (PDOException $e) {
        // Prepare error response if database update fails
        $error_message = 'Database error: ' . $e->getMessage();
        echo json_encode([
            'device_id' => $device_id,
            'count' => $device['download_count'],
            'isAccept' => false,
            'error' => $error_message
        ]);
    }
} else {
    // Device ID not found in the database
    $error_key = 'device_not_found';
    $error_message = getErrorMessage($lang_code, $error_key);
    echo json_encode(['error' => $error_message]);
}

