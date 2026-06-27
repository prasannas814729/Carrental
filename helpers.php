<?php
// ============================================================
//  RentX — Global Helpers & Auth
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

// ── Session helpers ──────────────────────────────────────────
function isLoggedIn(): bool  { return isset($_SESSION['user_id']); }
function currentUser(): ?array { return $_SESSION['user'] ?? null; }
function userRole(): string  { return $_SESSION['user']['role'] ?? ''; }

function requireLogin(string $redirect = '/rentx/index.php'): void {
    if (!isLoggedIn()) { header("Location: $redirect"); exit; }
}

function requireRole(string $redirect = '/rentx/index.php', string ...$roles): void {
    requireLogin($redirect);
    if (!in_array(userRole(), $roles, true)) { header("Location: $redirect"); exit; }
}

// ── Flash messages ───────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function showFlash(): void {
    $f = getFlash();
    if (!$f) return;
    $cls = $f['type'] === 'success' ? 'alert-success' : ($f['type'] === 'error' ? 'alert-error' : 'alert-info');
    echo "<div class='alert $cls'>" . htmlspecialchars($f['msg']) . "</div>";
}

// ── Sanitize ─────────────────────────────────────────────────
function s(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

// ── Notifications ────────────────────────────────────────────
function addNotif(int $userId, string $title, string $message): void {
    $db = getDB();
    $db->prepare("INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)")
       ->execute([$userId, $title, $message]);
}

function notifCount(): int {
    if (!isLoggedIn()) return 0;
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}

function notifyAllAdmins(string $title, string $message): void {
    $db    = getDB();
    $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','owner')")->fetchAll();
    foreach ($admins as $a) addNotif($a['id'], $title, $message);
}

// ── File upload helper ────────────────────────────────────────
function uploadImage(array $file, string $dir = 'uploads/') : ?string {

    $allowed = ['image/jpeg','image/png','image/webp'];

    // ❌ validation
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 3 * 1024 * 1024) return null;

    // ✅ get extension
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = uniqid('car_', true) . '.' . $ext;

    // ✅ FIXED PATH (MAIN FIX)
    $uploadDir = dirname(__DIR__) . '/' . $dir;

    // ✅ CREATE FOLDER IF NOT EXISTS
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fullPath = $uploadDir . $name;

    // ✅ MOVE FILE
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return $dir . $name; // save relative path
    }

    return null;
}
function formatINR($amount) {
    return '₹' . number_format($amount, 2);
}