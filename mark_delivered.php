<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// mark delivered
$db->prepare("UPDATE bookings 
    SET delivery_status='delivered' 
    WHERE id=?")
   ->execute([$id]);

setFlash('success', 'Car marked as delivered.');
header("Location: /rentx/admin/booking_detail.php?id=".$id);
exit;
?>