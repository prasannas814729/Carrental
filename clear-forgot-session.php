<?php
// ============================================================
//  RentX — Clear Forgot Password Session
// ============================================================
require_once __DIR__ . '/includes/helpers.php';

unset($_SESSION['forgot_user_id']);
unset($_SESSION['forgot_email']);
unset($_SESSION['forgot_role']);
unset($_SESSION['reset_token']);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['status' => 'cleared']);
?>
