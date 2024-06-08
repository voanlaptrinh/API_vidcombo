<?php

use PHPMailer\PHPMailer\PHPMailer;

try {
    $conn = new PDO("mysql:host=localhost; dbname=api_vidcombo; charset=utf8;", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Lỗi kết nối đến cơ sở dữ liệu: " . $e->getMessage();
}


function getDatabaseConnection() {
    try {
       
        $pdo = new PDO("mysql:host=localhost; dbname=api_vidcombo; charset=utf8;", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Connection failed: ' . $e->getMessage());
        die('Database connection failed.');
    }
}

function sendEmailNotification($to, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'thanbatbai3092002@gmail.com';
        $mail->Password = 'etejnwheciweprdo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($to, 'Mailer');
        $mail->addAddress($to, 'ádasdasd');

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
    } catch (Exception $e) {
        error_log('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
    }
}

