<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin', 'owner');

$db     = getDB();
$pid    = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';
$uid    = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT p.*, b.user_id AS renter_id, b.owner_confirmed, b.user_confirmed, b.owner_return, b.user_return
    FROM payments p
    JOIN bookings b ON b.id = p.booking_id
    WHERE p.id = ?
");
$stmt->execute([$pid]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('error', 'Payment not found.');
    header('Location: /rentx/admin/payments.php');
    exit;
}

$bid = $payment['booking_id'];

// ── confirm ───────────────────────────────────────────────────────────────────
if ($action === 'confirm') {

    $db->prepare("UPDATE payments
                  SET status='confirmed', confirmed_by=?, confirmed_at=NOW()
                  WHERE id=? AND status='pending'")
       ->execute([$uid, $pid]);

    $db->prepare("UPDATE bookings SET payment_status='confirmed' WHERE id=?")
       ->execute([$bid]);

    addNotif($payment['renter_id'], 'Payment Confirmed',
        "Your payment for booking #{$bid} has been confirmed.");
    setFlash('success', 'Payment confirmed successfully.');

// ── fail ──────────────────────────────────────────────────────────────────────
} elseif ($action === 'fail') {

    $db->prepare("UPDATE payments SET status='failed' WHERE id=?")
       ->execute([$pid]);

    addNotif($payment['renter_id'], 'Payment Failed',
        "Your payment for booking #{$bid} could not be verified. Please contact support.");
    setFlash('error', 'Payment marked as failed.');

// ── refund ────────────────────────────────────────────────────────────────────
} elseif ($action === 'refund') {

    $db->prepare("UPDATE payments SET status='refunded' WHERE id=?")
       ->execute([$pid]);

    addNotif($payment['renter_id'], 'Payment Refunded',
        "Your payment for booking #{$bid} has been refunded.");
    setFlash('success', 'Payment marked as refunded.');

// ── release ───────────────────────────────────────────────────────────────────
} elseif ($action === 'release') {

    // Guard: only release when both parties confirmed delivery AND return
    if (!($payment['owner_confirmed'] && $payment['user_confirmed']
       && $payment['owner_return']    && $payment['user_return'])) {
        setFlash('error', 'Cannot release — delivery or return not fully confirmed.');
        header('Location: /rentx/admin/payments.php');
        exit;
    }

    // 1. Update payment
    $db->prepare("UPDATE payments SET status='released' WHERE id=?")
       ->execute([$pid]);

    // 2. Update booking  — note: fixed SQL bug (was `WHERE id=?)` with stray paren)
    $db->prepare("UPDATE bookings SET payment_status='released' WHERE id=?")
       ->execute([$bid]);

    // 3. Notify renter
    addNotif($payment['renter_id'], 'Payment Released',
        "Payment for booking #{$bid} has been released to the owner.");

    setFlash('success', 'Payment released to owner.');
    header('Location: /rentx/admin/payments.php');
    exit;
}

header("Location: /rentx/admin/booking_detail.php?id=$bid");
exit;