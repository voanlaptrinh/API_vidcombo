<?php
header('Content-Type: application/json');

$latestVersion = "1.2.0";
$ytdlpVersion = "2024.07.01";
$ffmpegVersion = "7.0.1";

$releaseNotes = "Đã sửa lỗi và có bản cập nhật";

$os = isset($_GET['os'])?strtolower($_GET['os']):'';
$currentVersion = isset($_GET['current_version'])?$_GET['current_version']:'';
$ytdlp_version = isset($_GET['ytdlp_version'])?$_GET['ytdlp_version']:'';
$ffmpeg_version = isset($_GET['ffmpeg_version'])?$_GET['ffmpeg_version']:'';

if ($currentVersion && $ytdlp_version && $ffmpeg_version && $os) {
    if ($os == 'windows64') {
        $downloadUrl = "https://www.vidcombo.com/";
        $downloadYtdlp = "https://api.vidcombo.com/download/ytdlp/2024-07-01/yt-dlp_x64.zip";
        $downloadFfmpeg = "https://api.vidcombo.com/download/ffmpeg-7.0.1_x64.zip";
    } elseif ($os == 'windows86' || $os == 'windows32') {
        $downloadUrl = "https://www.vidcombo.com/";
        $downloadYtdlp = "https://api.vidcombo.com/download/ytdlp/2024-07-01/yt-dlp_x86.zip";
        $downloadFfmpeg = "https://api.vidcombo.com/download/ffmpeg-7.0.1_x32.zip";
    } elseif ($os == 'macos') {
        $downloadUrl = "https://www.vidcombo.com/";
        $downloadYtdlp = "https://api.vidcombo.com/download/ytdlp/2024-07-01/yt-dlp_macos.zip";
        $downloadFfmpeg = "https://api.vidcombo.com/download/ffmpeg-7.0.1_macos.zip";
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

