<?php

header('Content-Type: application/json');

$latestVersion = "1.2.0";

$releaseNotes = "Đã sửa lỗi và có bản cập nhật";

$ytdlpVersion = "1.2.0";
$ffmpegVersion = "1.2.0";

if (isset($_GET['current_version']) && isset($_GET['ytdlp_version']) && isset($_GET['ffmpeg_version']) && isset($_GET['os'])) {
    $currentVersion = $_GET['current_version'];
    $ytdlp_version = $_GET['ytdlp_version'];
    $ffmpeg_version = $_GET['ffmpeg_version'];
    if ($_GET['os'] == 'Windows64') {
        $downloadUrl = "https://example.com/download/Windows64/app-v1.2.0.apk";
        $downloadYtdlp = "https://example.com/downloadYtdlp/Windows64/app-v1.2.0.apk";
        $downloadFfmpeg = "https://example.com/downloadFfmpeg/Windows64/app-v1.2.0.apk";
    } elseif ($_GET['os'] == 'Windows86' || $_GET['os'] == 'Windows32') {
        $downloadUrl = "https://example.com/download/Windows86-32/app-v1.2.0.apk";
        $downloadYtdlp = "https://example.com/downloadYtdlp/Windows86-32/app-v1.2.0.apk";
        $downloadFfmpeg = "https://example.com/downloadFfmpeg/Windows86-32/app-v1.2.0.apk";
    } elseif ($_GET['os'] == 'MacOs') {
        $downloadUrl = "https://example.com/download/MacOS/app-v1.2.0.apk";
        $downloadYtdlp = "https://example.com/downloadYtdlp/MacOS/app-v1.2.0.apk";
        $downloadFfmpeg = "https://example.com/downloadFfmpeg/MacOS/app-v1.2.0.apk";
    } else {
        $response = [
            'error' => true,
            'message' => 'OS không hợp lệ.'
        ];
        echo json_encode($response);
        exit;
    }
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
            'download_url' => $downloadYtdlp, 
            'release_notes' => $releaseNotes,
        ],
        "ffmpeg" => [
            'update' => $ffmpegUpdateAvailable,
            'ffmpeg_version' => $ffmpegVersion,
            'download_url' => $downloadFfmpeg, 
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

