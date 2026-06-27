<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'user');

$db = getDB();
$bid = (int)$_GET['id'];
$type = $_GET['type'] ?? 'delivery';

if ($type === 'delivery') {

    $db->prepare("UPDATE bookings SET user_confirmed=1 WHERE id=?")
       ->execute([$bid]);

    setFlash('success','Delivery confirmed');

} elseif ($type === 'return') {

    $db->prepare("UPDATE bookings SET user_return=1 WHERE id=?")
       ->execute([$bid]);

    setFlash('success','Return submitted');
}

header("Location: /rentx/user/booking_detail.php?id=$bid");
exit;