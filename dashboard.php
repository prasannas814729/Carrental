<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'user');
$pageTitle = 'My Dashboard';

$db   = getDB();
$uid  = $_SESSION['user_id'];
$user = currentUser();

$totalBookings = $db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=?"); $totalBookings->execute([$uid]); $totalBookings = $totalBookings->fetchColumn();
$activeBookings = $db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status IN ('confirmed','active')"); $activeBookings->execute([$uid]); $activeBookings = $activeBookings->fetchColumn();
$pendingBookings = $db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status='pending'"); $pendingBookings->execute([$uid]); $pendingBookings = $pendingBookings->fetchColumn();
$totalSpent = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.user_id=? AND p.status='confirmed'"); $totalSpent->execute([$uid]); $totalSpent = $totalSpent->fetchColumn();

$recentBookings = $db->prepare("
    SELECT b.*, c.brand, c.model, c.image, c.price_day FROM bookings b
    JOIN cars c ON c.id = b.car_id WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5
");
$recentBookings->execute([$uid]);
$recentBookings = $recentBookings->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">Welcome back, <?= s(explode(' ', $user['name'])[0]) ?>!</div>
      <div class="section-sub">Here's a snapshot of your activity.</div>
    </div>
    <a href="/rentx/cars.php" class="btn btn-primary">Browse Cars</a>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Bookings</div>
      <div class="stat-value"><?= $totalBookings ?></div>
    </div>
    <div class="stat-card blue">
      <div class="stat-label">Active Rentals</div>
      <div class="stat-value"><?= $activeBookings ?></div>
    </div>
    <div class="stat-card" style="border-left-color:var(--gold)">
      <div class="stat-label">Pending Approval</div>
      <div class="stat-value"><?= $pendingBookings ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Total Spent</div>
      <div class="stat-value"><?= formatINR($totalSpent) ?></div>
    </div>
  </div>

  <div class="section-header mt-4">
    <div class="section-title">Recent Bookings</div>
    <a href="/rentx/user/my_bookings.php" class="btn btn-secondary btn-sm">View All</a>
  </div>

  <?php if (empty($recentBookings)): ?>
    <div class="card text-center" style="padding:3rem">
      <div style="font-size:3rem">🚗</div>
      <h3 class="mt-2">No bookings yet</h3>
      <p class="text-muted mt-1">Find your perfect car and make your first booking!</p>
      <a href="/rentx/cars.php" class="btn btn-primary mt-3">Browse Cars</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Car</th><th>Dates</th><th>Days</th><th>Total</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td>
              <strong><?= s($b['brand']) ?> <?= s($b['model']) ?></strong><br>
              <span class="text-muted text-xs"><?= formatINR($b['price_day']) ?>/day</span>
            </td>
            <td><?= s($b['start_date']) ?> → <?= s($b['end_date']) ?></td>
            <td><?= $b['total_days'] ?></td>
            <td class="text-gold"><strong><?= formatINR($b['total_price']) ?></strong></td>
            <td><span class="status status-<?= s($b['status']) ?>"><?= ucfirst(s($b['status'])) ?></span></td>
            <td><a href="/rentx/user/booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn-sm">Details</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
