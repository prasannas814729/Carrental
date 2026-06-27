<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'user');
$pageTitle = 'My Bookings';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bid = (int)$_POST['booking_id'];
    $chk = $db->prepare("SELECT * FROM bookings WHERE id=? AND user_id=? AND status='pending'");
    $chk->execute([$bid, $uid]);
    if ($chk->fetch()) {
        $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$bid]);
        notifyAllAdmins('Booking Cancelled', "User cancelled booking #{$bid}.");
        setFlash('success', 'Booking cancelled.');
    } else {
        setFlash('error', 'Cannot cancel this booking.');
    }
    header('Location: /rentx/user/my_bookings.php'); exit;
}

$filter = $_GET['status'] ?? '';
$where  = ['b.user_id = ?'];
$params = [$uid];
if ($filter) { $where[] = "b.status = ?"; $params[] = $filter; }

$stmt = $db->prepare("
    SELECT b.*, c.brand, c.model, c.image, p.status AS payment_status
    FROM bookings b JOIN cars c ON c.id = b.car_id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">My Bookings</div>
      <div class="section-sub"><?= count($bookings) ?> booking(s)</div>
    </div>
    <a href="/rentx/cars.php" class="btn btn-primary">+ New Booking</a>
  </div>

  <form method="GET" class="filters-bar auto-submit mb-2">
    <div class="form-group">
      <label>Filter by Status</label>
      <select name="status" class="form-control">
        <option value="">All Statuses</option>
        <?php foreach (['pending','confirmed','active','completed','cancelled'] as $st): ?>
          <option value="<?= $st ?>" <?= $filter===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if (empty($bookings)): ?>
    <div class="card text-center" style="padding:3rem">
      <div style="font-size:3rem">📋</div>
      <h3 class="mt-2">No bookings found</h3>
      <a href="/rentx/cars.php" class="btn btn-primary mt-3">Browse Cars</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Car</th><th>Dates</th><th>Days</th><th>Total</th><th>Booking</th><th>Payment</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><strong><?= s($b['brand']) ?> <?= s($b['model']) ?></strong></td>
            <td><?= s($b['start_date']) ?><br><span class="text-muted text-xs">to <?= s($b['end_date']) ?></span></td>
            <td><?= $b['total_days'] ?></td>
            <td class="text-gold"><strong><?= formatINR($b['total_price']) ?></strong></td>
            <td><span class="status status-<?= s($b['status']) ?>"><?= ucfirst(s($b['status'])) ?></span></td>
            <td><span class="status status-<?= s($b['payment_status'] ?? 'pending') ?>"><?= ucfirst(s($b['payment_status'] ?? 'pending')) ?></span></td>
            <td style="display:flex;gap:.4rem;flex-wrap:wrap">
              <a href="/rentx/user/booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn-sm">Details</a>
              <?php if ($b['status'] === 'pending'): ?>
                <form method="POST">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this booking?')">Cancel</button>
                </form>
              <?php endif; ?>
              <?php if ($b['status'] === 'confirmed' && $b['payment_status'] === 'pending'): ?>
                <a href="/rentx/user/payment.php?booking_id=<?= $b['id'] ?>" class="btn btn-success btn-sm">Pay Now</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
