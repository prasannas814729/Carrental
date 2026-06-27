<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin');

$db = getDB();

// This is PAYMENT ID (not booking id)
$payment_id = (int)($_GET['id'] ?? 0);

// 1. Get booking_id from payments
$stmt = $db->prepare("SELECT booking_id FROM payments WHERE id=?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Payment not found");
}

$booking_id = $payment['booking_id'];

// 2. Update payments table
$db->prepare("UPDATE payments 
              SET status='released' 
              WHERE id=?")
   ->execute([$payment_id]);

// 3. Update bookings table
$db->prepare("UPDATE bookings 
              SET payment_status='released' 
              WHERE id=?")
   ->execute([$booking_id]);

setFlash('success', 'Payment released to owner.');

header("Location: /rentx/admin/payments.php");
exit;
?>