<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'owner');
$pageTitle = 'My Cars';

$db  = getDB();
$uid = $_SESSION['user_id'];

$statusFilter = $_GET['status'] ?? '';
$where  = ['c.owner_id = ?'];
$params = [$uid];
if ($statusFilter) { $where[] = "c.status = ?"; $params[] = $statusFilter; }

$stmt = $db->prepare("SELECT * FROM cars c WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC");
$stmt->execute($params);
$cars = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">My Cars</div>
      <div class="section-sub"><?= count($cars) ?> car(s)</div>
    </div>
    <a href="/rentx/owner/add_car.php" class="btn btn-primary">+ Add Car</a>
  </div>

  <form method="GET" class="filters-bar auto-submit mb-2">
    <div class="form-group">
      <label>Filter by Status</label>
      <select name="status" class="form-control">
        <option value="">All</option>
        <?php foreach (['pending','available','rented','maintenance','rejected'] as $st): ?>
          <option value="<?= $st ?>" <?= $statusFilter===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if (empty($cars)): ?>
    <div class="card text-center" style="padding:3rem">
      <div style="font-size:3rem">🚗</div>
      <h3 class="mt-2">No cars yet</h3>
      <a href="/rentx/owner/add_car.php" class="btn btn-primary mt-3">Add Your First Car</a>
    </div>
  <?php else: ?>
    <div class="cars-grid">
      <?php foreach ($cars as $c): ?>
        <div class="car-card">
          <?php if ($c['image']): ?>
            <img src="/rentx/<?= s($c['image']) ?>" class="car-card-img">
          <?php else: ?>
            <div class="car-card-img-placeholder">🚗</div>
          <?php endif; ?>
          <div class="car-card-body">
            <div class="car-card-title"><?= s($c['brand']) ?> <?= s($c['model']) ?></div>
            <div class="car-card-meta">
              <span class="tag"><?= s($c['year']) ?></span>
              <span class="tag"><?= ucfirst(s($c['category'])) ?></span>
              <span class="status status-<?= s($c['status']) ?>"><?= ucfirst(s($c['status'])) ?></span>
            </div>
            <div class="car-price"><?= formatINR($c['price_day']) ?> <span>/ day</span></div>
          </div>
          <div class="car-card-actions">
            <a href="/rentx/car_detail.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">View</a>
            <?php if ($c['status'] === 'pending'): ?>
              <span class="text-muted text-xs" style="padding:.4rem">⏳ Awaiting approval</span>
            <?php elseif ($c['status'] === 'rejected'): ?>
              <span class="text-red text-xs" style="padding:.4rem">✗ Rejected</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
