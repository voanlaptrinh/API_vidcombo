<?php

header('Content-Type: application/json');

$latestVersion = "1.2.0";
$downloadUrl = "https://example.com/download/app-v1.2.0.apk";
$releaseNotes = "Đã sửa lỗi và có bản cập nhật";

if (isset($_GET['current_version'])) {
    $currentVersion = $_GET['current_version'];

    if (version_compare($currentVersion, $latestVersion, '!=')) {

        $response = [
            'update' => true,
            'latest_version' => $latestVersion,
            'download_url' => $downloadUrl,
            'release_notes' => $releaseNotes
        ];
    } else {

        $response = [
            'update' => false,
            'message' => 'Bạn đang sử dụng phiên bản mới nhất.'
        ];
    }
} else {

    $response = [
        'error' => true,
        'message' => 'Không cung cấp'
    ];
}

// Trả về phản hồi dưới dạng JSON
echo json_encode($response);

