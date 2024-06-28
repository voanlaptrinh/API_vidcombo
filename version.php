<?php

header('Content-Type: application/json');

$latestVersion = "1.2.0";
$downloadUrl = "https://example.com/download/app-v1.2.0.apk";
$releaseNotes = "Đã sửa lỗi và có bản cập nhật";

$ytdlpVersion = "1.2.0";
$ffmpegVersion = "1.2.0";

if (isset($_GET['current_version']) && isset($_GET['ytdlp_version']) && isset($_GET['ffmpeg_version'])) {
    $currentVersion = $_GET['current_version'];
    $ytdlp_version = $_GET['ytdlp_version'];
    $ffmpeg_version = $_GET['ffmpeg_version'];

    $appUpdateAvailable = version_compare($currentVersion, $latestVersion, '!=');
    $ytdlpUpdateAvailable = version_compare($ytdlp_version, $ytdlpVersion, '!=');
    $ffmpegUpdateAvailable = version_compare($ffmpeg_version, $ffmpegVersion, '!=');

    $response = [
        'update' => $appUpdateAvailable ,
        'latest_version' => $latestVersion,
        'download_url' => $downloadUrl,
        'release_notes' => $releaseNotes,
        "ytdlp" => [
            'update' => $ytdlpUpdateAvailable,
            'ytdlp_version' => $ytdlpVersion,
            'download_url' => $downloadUrl, 
            'release_notes' => $releaseNotes,
        ],
        "ffmpeg" => [
            'update' => $ffmpegUpdateAvailable,
            'ffmpeg_version' => $ffmpegVersion,
            'download_url' => $downloadUrl, 
            'release_notes' => $releaseNotes, 
        ],
    ];

    if (!$appUpdateAvailable && !$ytdlpUpdateAvailable && !$ffmpegUpdateAvailable) {
        $response['message'] = 'Bạn đang sử dụng phiên bản mới nhất.';
    }
} else {
    $response = [
        'error' => true,
        'message' => 'Không cung cấp đủ thông tin phiên bản.'
    ];
}


echo json_encode($response);

