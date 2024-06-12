<?php
require_once '../vendor/autoload.php';

// Khóa bí mật của Stripe
\Stripe\Stripe::setApiKey('sk_test_51OeDsPIXbeKO1uxjfGZLmBaoVYMdmbThMwRHSrNa6Zigu0FnQYuAatgfPEodv9suuRFROdNRHux5vUhDp7jC6nca00GbHqdk1Y');

// Nhận session_id từ request
$session_id = $_GET['session_id'] ?? null;

if ($session_id) {
    try {
        // Lấy thông tin chi tiết về phiên từ Stripe
        $session = \Stripe\Checkout\Session::retrieve($session_id);

        // Lấy subscription ID từ phiên nếu có
        $subscription_id = $session->subscription;
        // Nếu có subscription, lấy thêm thông tin chi tiết
        if ($subscription_id) {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            $current_period_end = date('Y-m-d H:i:s', $subscription->ended_at);

            $session_details = [
                'session_id' => $session->id,
                'subscription_id' => $subscription->id,
                'current_period_end' => $current_period_end,
                'status' => $subscription->status,
            ];
        } else {
            $session_details = [
                'session_id' => $session->id,
                'status' => 'No subscription found',
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($session_details);
    } catch (\Exception $e) {
        error_log($e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'session_id is required']);
}
?>
