<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

if (!is_post()) {
    redirect('bookings.php');
}
csrf_verify();

$bookingId = oid($_POST['booking_id'] ?? null);
$method    = (string)($_POST['method'] ?? 'upi');
if (!isset(payment_methods()[$method])) {
    $method = 'upi';
}

$done = $bookingId ? complete_booking($bookingId, $user['_id'], $method) : null;

if (!$done) {
    flash('danger', 'That booking could not be checked out. It may already be completed.');
    redirect('bookings.php');
}

flash('success', 'Payment of ' . money((float)$done['total']) . ' received. Safe travels!');
redirect('receipt.php?id=' . $done['_id']);
