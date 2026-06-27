<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'owner');
$pageTitle = 'Revenue';

$db  = getDB();
$uid = $_SESSION['user_id'];

$totalRevenue    = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN cars c ON c.id=b.car_id WHERE c.owner_id=? AND p.status='confirmed' AND b.payment_status='released'"); $totalRevenue->execute([$uid]); $totalRevenue = $totalRevenue->fetchColumn();
$pendingRevenue  = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN cars c ON c.id=b.car_id WHERE c.owner_id=? AND p.status='confirmed' AND b.payment_status!='released'");   $pendingRevenue->execute([$uid]); $pendingRevenue = $pendingRevenue->fetchColumn();
$totalBookings   = $db->prepare("SELECT COUNT(*) FROM bookings b JOIN cars c ON c.id=b.car_id WHERE c.owner_id=? AND b.status='completed'"); $totalBookings->execute([$uid]); $totalBookings = $totalBookings->fetchColumn();

// Monthly revenue (last 6 months)
$monthly = $db->prepare("
SELECT DATE_FORMAT(p.created_at, '%Y-%m') AS month,
       SUM(p.amount) AS total
FROM payments p
JOIN bookings b ON b.id = p.booking_id
JOIN cars c ON c.id = b.car_id
WHERE p.status = 'confirmed'
AND b.payment_status='released'
AND c.owner_id = ?
GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
ORDER BY month ASC
");

$monthly->execute([$uid]);
$monthly = $monthly->fetchAll(PDO::FETCH_ASSOC);

// Fill missing months (last 6 months)
$monthsData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthsData[$month] = 0;
}

foreach ($monthly as $m) {
    $monthsData[$m['month']] = $m['total'];
}

$monthly = [];
foreach ($monthsData as $month => $total) {
    $monthly[] = ['month' => $month, 'total' => $total];
}

// Per-car revenue
$perCar = $db->prepare("
    SELECT c.brand, c.model, c.plate, COUNT(b.id) AS bookings,
           COALESCE(SUM(p.amount),0) AS revenue
    FROM cars c
    LEFT JOIN bookings b  ON b.car_id = c.id AND b.status = 'completed'
    LEFT JOIN payments p  ON p.booking_id = b.id AND p.status = 'confirmed' AND b.payment_status='released'
    WHERE c.owner_id = ?
    GROUP BY c.id ORDER BY revenue DESC
");
$perCar->execute([$uid]);
$perCar = $perCar->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">Revenue Report</div>
      <div class="section-sub">Your earnings summary</div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card green">
      <div class="stat-label">Confirmed Revenue</div>
      <div class="stat-value"><?=formatINR($totalRevenue) ?></div>
    </div>
    <div class="stat-card" style="border-left-color:var(--gold)">
      <div class="stat-label">Pending Revenue</div>
      <div class="stat-value"><?= formatINR($pendingRevenue) ?></div>
    </div>
    <div class="stat-card blue">
      <div class="stat-label">Completed Rentals</div>
      <div class="stat-value"><?= $totalBookings ?></div>
    </div>
  </div>

  <?php if (!empty($monthly)): ?>
  <div class="card mt-4">
    <h3 class="mb-3">Monthly Revenue (Last 6 Months)</h3>
    <div style="display:flex;align-items:flex-end;gap:.75rem;height:160px;padding-bottom:.5rem">
      <?php
      $maxVal = max(array_column($monthly, 'total') ?: [1]);
      foreach ($monthly as $m):
        $height =($maxVal > 0) ?max(10, round(($m['total'] / $maxVal) * 140)) : 10;
        $label  = date('M Y', strtotime($m['month'] . '-01'));
      ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.5rem">
          <span class="text-xs text-gold"><?= formatINR($m['total']) ?></span>
          <div style="width:100%;height:<?= $height ?>px;background:var(--gold);border-radius:4px 4px 0 0;opacity:.8"></div>
          <span class="text-xs text-muted" style="white-space:nowrap;font-size:.65rem"><?= $label ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="section-header mt-4">
    <div class="section-title">Revenue Per Car</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Car</th><th>Plate</th><th>Completed Bookings</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($perCar as $c): ?>
        <tr>
          <td><strong><?= s($c['brand']) ?> <?= s($c['model']) ?></strong></td>
          <td><?= s($c['plate']) ?></td>
          <td><?= $c['bookings'] ?></td>
          <td class="text-gold"><strong><?= formatINR($c['revenue']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
