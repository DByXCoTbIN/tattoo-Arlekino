<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\BookingRepository;

Auth::init();
header('Content-Type: application/json');

$user = Auth::user();
if (!$user || $user['role'] !== 'client') {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
if (!$bookingId) {
    echo json_encode(['ok' => false, 'error' => 'missing']);
    exit;
}

$repo = new BookingRepository();
if ($repo->dismissReviewRequest($bookingId, (int)$user['id'])) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'failed']);
}
