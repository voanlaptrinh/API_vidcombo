<?php
// require_once 'redis.php';
// require_once './common.php';
use App\Common;
use App\RedisCache;
use App\Models\DB;
// Retrieve parameters from request
$device_id = urldecode(Common::getString('device_id'));;
$count = Common::getInt('count');
$lang_code = Common::getString('lang_code', 'en');

if (!$device_id || !$count) {
    http_response_code(400);
    // Handle missing parameters error
    $error_key = 'missing_parameters';
    print_mess(array('message' => Common::getErrorMessage($lang_code, $error_key)));
}

//set cache
$redis = new RedisCache('LIMITED_' . $device_id);
$cache = $redis->getCache();
if ($cache) {
    $data = array('device_id' => $device_id, 'count' => 0, 'isAccept' => false, 'message' => Common::getErrorMessage($lang_code, 'download_limit_reached'));
    print_mess($data);
}


// Check if the device ID exists in the database
// $connection = Common::getDatabaseConnection();
// if (!$connection) {
//     throw new Exception('Database connection could not be established.');
// }
$selectSub = new DB();
$selectSub->setTable('device');
$dataSubupdate = [
    'download_count'
];
$device = $selectSub->selectRow($dataSubupdate, ['device_id' => $device_id]);
// $stmt = $connection->prepare("SELECT `download_count` FROM `device` WHERE `device_id` = :device_id");
// $stmt->execute([':device_id' => $device_id]);
// $device = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($device['download_count'])) {
    $download_count = intval($device['download_count']);
    if (!$download_count) {
        $redis->setCache(time(), 3600); // Cache for 1hour
        $data = array('device_id' => $device_id, 'count' => 0, 'isAccept' => false, 'message' => Common::getErrorMessage($lang_code, 'download_limit_reached'));
        print_mess($data);
    }
    try {
        // Calculate new download count
        $new_download_count = max($download_count - $count, 0); // Ensure download count doesn't go negative

        // Update the device record with the new download count
        $updatedevice = new DB();
        $updatedevice->setTable('device');
        $datadevice = [
          'new_download_count' => $new_download_count,
        ];
       $updatedevice->selectRow($datadevice, ['device_id' => $device_id]);
       

        // Prepare success response
        $error_key = 'device_information';
        $data = array(
            'device_id' => $device_id,
            'count' => $new_download_count,
            'isAccept' => true,
            'message' => Common::getErrorMessage($lang_code, $error_key)
        );
        print_mess($data);
    } catch (PDOException $e) {
        // Prepare error response if database update fails
        $data = array(
            'device_id' => $device_id,
            'count' => $device['download_count'],
            'isAccept' => false,
            'message' => 'Something went wrong!'
        );
        print_mess($data);
    }
} else {
    // Device ID not found in the database
    $error_key = 'device_not_found';
    print_mess(array('message' => Common::getErrorMessage($lang_code, $error_key)));
}

function print_mess($data)
{
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}
