<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin', 'owner');
$pageTitle = 'Payments';

$db     = getDB();
$filter = $_GET['status'] ?? '';

$where  = []; $params = [];
if ($filter) { $where[] = "p.status = ?"; $params[] = $filter; }

$sql = "SELECT p.*, b.start_date, b.end_date, b.total_days,
               b.owner_confirmed, b.user_confirmed, b.owner_return, b.user_return,
               c.brand, c.model,
               u.name AS user_name, u.email AS user_email
        FROM payments p
        JOIN bookings b ON b.id = p.booking_id
        JOIN cars c     ON c.id = b.car_id
        JOIN users u    ON u.id = p.user_id";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql); $stmt->execute($params);
$payments = $stmt->fetchAll();

$totals = $db->query("SELECT status, SUM(amount) as total FROM payments GROUP BY status")
             ->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/../includes/header.php';
?>
<style>
.receipt-thumb {
    width:56px; height:56px; object-fit:cover; border-radius:6px;
    border:1px solid var(--border); cursor:pointer; transition:.15s;
}
.receipt-thumb:hover { transform:scale(1.08); }

/* Lightbox */
#lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
    z-index:9999; align-items:center; justify-content:center; }
#lightbox.open { display:flex; }
#lightbox img { max-width:90vw; max-height:90vh; border-radius:8px; }
#lightbox .close-lb {
    position:absolute; top:1rem; right:1.5rem; color:#fff;
    font-size:2rem; cursor:pointer; background:none; border:none;
}
.utr-pill {
    display:inline-block; background:rgba(212,175,55,.12);
    border:1px solid var(--gold); color:var(--gold);
    padding:.2rem .6rem; border-radius:20px; font-size:.78rem; font-weight:600;
    letter-spacing:.03em; font-family:monospace;
}
</style>

<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">Payments</div>
      <div class="section-sub"><?= count($payments) ?> payment(s)</div>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="stats-grid mb-3">
    <div class="stat-card green">
      <div class="stat-label">Confirmed Revenue</div>
      <div class="stat-value"><?= formatINR($totals['confirmed'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= formatINR($totals['pending'] ?? 0) ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Failed</div>
      <div class="stat-value"><?= formatINR($totals['failed'] ?? 0) ?></div>
    </div>
    <div class="stat-card blue">
      <div class="stat-label">Refunded</div>
      <div class="stat-value"><?= formatINR($totals['refunded'] ?? 0) ?></div>
    </div>
  </div>

  <form method="GET" class="filters-bar auto-submit mb-2">
    <div class="form-group">
      <label>Filter by Status</label>
      <select name="status" class="form-control">
        <option value="">All</option>
        <?php foreach (['pending','confirmed','released','failed','refunded'] as $st): ?>
          <option value="<?= $st ?>" <?= $filter===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th>
        <th>Booking</th>
        <th>User</th>
        <th>Car</th>
        <th>Amount</th>
        <th>Method</th>
        <th>UTR / Reference</th>
        <th>Receipt</th>
        <th>Status</th>
        <th>Delivery / Return</th>
        <th>Date</th>
        <th>Action</th>
      </tr></thead>
      <tbody>
      <?php if (empty($payments)): ?>
        <tr><td colspan="12" class="text-center text-muted" style="padding:2rem">No payments found.</td></tr>
      <?php endif; ?>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td>#<?= $p['id'] ?></td>

          <td>
            <a href="/rentx/admin/booking_detail.php?id=<?= $p['booking_id'] ?>" class="text-gold">
              #<?= $p['booking_id'] ?>
            </a>
          </td>

          <td>
            <?= s($p['user_name']) ?><br>
            <span class="text-xs text-muted"><?= s($p['user_email']) ?></span>
          </td>

          <td><?= s($p['brand']) ?> <?= s($p['model']) ?></td>

          <td class="text-gold"><strong><?= formatINR($p['amount']) ?></strong></td>

          <td>
            <?php
              $method_label = [
                  'upi_qr'       => '📱 UPI / QR',
                  'cash'         => '💵 Cash',
                  'bank_transfer'=> '🏦 Bank',
                  'online'       => '🌐 Online',
              ];
              echo $method_label[$p['method']] ?? ucfirst(str_replace('_',' ',$p['method'] ?? '—'));
            ?>
          </td>

          <!-- UTR / Reference -->
          <td>
            <?php if (!empty($p['reference'])): ?>
              <span class="utr-pill"><?= s($p['reference']) ?></span>
            <?php else: ?>
              <span class="text-muted text-xs">—</span>
            <?php endif; ?>
          </td>

          <!-- Receipt screenshot -->
          <td>
            <?php if (!empty($p['receipt'])): ?>
              <img src="/rentx/<?= s($p['receipt']) ?>"
                   class="receipt-thumb"
                   alt="Receipt"
                   onclick="openLightbox(this.src)"
                   title="Click to enlarge">
            <?php else: ?>
              <span class="text-muted text-xs">—</span>
            <?php endif; ?>
          </td>

          <td>
            <span class="status status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
          </td>

          <!-- Delivery / Return status -->
          <td style="font-size:.8rem;line-height:1.6">
            <strong>Delivery:</strong><br>
            Owner: <?= $p['owner_confirmed'] ? '✔' : '❌' ?> &nbsp;
            User: <?= $p['user_confirmed'] ? '✔' : '❌' ?><br>
            <?php if ($p['owner_confirmed'] && $p['user_confirmed']): ?>
              <span style="color:green">Delivered ✅</span>
            <?php else: ?>
              <span style="color:orange">Waiting</span>
            <?php endif; ?>

            <br><strong>Return:</strong><br>
            Owner: <?= $p['owner_return'] ? '✔' : '❌' ?> &nbsp;
            User: <?= $p['user_return'] ? '✔' : '❌' ?><br>
            <?php if ($p['owner_confirmed'] && $p['user_confirmed']): ?>
              <?php if ($p['owner_return'] && $p['user_return']): ?>
                <span style="color:green">Returned ✅</span>
              <?php else: ?>
                <span style="color:orange">Waiting</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:gray">Not started</span>
            <?php endif; ?>
          </td>

          <td class="text-xs text-muted"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>

          <!-- Admin actions -->
          <td>
            <?php if ($p['status'] === 'pending'): ?>

              <a href="/rentx/admin/payment_confirm.php?id=<?= $p['id'] ?>&action=confirm"
                 class="btn btn-success btn-sm"
                 onclick="return confirm('Confirm this payment?')">✓ Confirm</a>

              <a href="/rentx/admin/payment_confirm.php?id=<?= $p['id'] ?>&action=fail"
                 class="btn btn-danger btn-sm"
                 onclick="return confirm('Mark as failed?')"
                 style="margin-left:.3rem">✗ Fail</a>

            <?php elseif ($p['status'] === 'confirmed'): ?>

              <?php if ($p['owner_confirmed'] && $p['user_confirmed']): ?>

                <?php if ($p['owner_return'] && $p['user_return']): ?>
                  <a href="/rentx/admin/payment_confirm.php?id=<?= $p['id'] ?>&action=release"
                     class="btn btn-success btn-sm"
                     onclick="return confirm('Release payment to owner?')">
                     💸 Release
                  </a>
                <?php else: ?>
                  <span class="text-muted text-xs">Waiting for return</span>
                  <a href="/rentx/admin/payment_confirm.php?id=<?= $p['id'] ?>&action=refund"
                     class="btn btn-secondary btn-sm"
                     onclick="return confirm('Refund this payment?')"
                     style="margin-left:.3rem">↩ Refund</a>
                <?php endif; ?>

              <?php else: ?>
                <span class="text-muted text-xs">Waiting for delivery</span>
              <?php endif; ?>

            <?php else: ?>
              <span class="text-muted text-xs">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Receipt lightbox -->
<div id="lightbox" onclick="closeLightbox()">
  <button class="close-lb" onclick="closeLightbox()">✕</button>
  <img id="lbImg" src="" alt="Receipt">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lbImg').src = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>