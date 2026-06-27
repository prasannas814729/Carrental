<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin', 'owner');

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

// If action provided, process it directly
if ($action === 'approve' || $action === 'reject') {
    $stmt = $db->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$id]);
    $car  = $stmt->fetch();

    if (!$car) { setFlash('error', 'Car not found.'); header('Location: /rentx/admin/cars.php'); exit; }

    if ($action === 'approve') {
        $db->prepare("UPDATE cars SET status='available' WHERE id=?")->execute([$id]);
        addNotif($car['owner_id'], 'Car Approved', "Your car {$car['brand']} {$car['model']} has been approved and is now listed.");
        setFlash('success', 'Car approved and listed.');
        header('Location: /rentx/admin/cars.php'); exit;
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE cars SET status='rejected' WHERE id=?")->execute([$id]);
        addNotif($car['owner_id'], 'Car Rejected', "Your car {$car['brand']} {$car['model']} was rejected. Please contact support.");
        setFlash('error', 'Car has been rejected.');
        header('Location: /rentx/admin/cars.php'); exit;
    }
}

// No action: show review page with owner documents
$stmt = $db->prepare("
    SELECT c.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone,
           u.driving_licence AS owner_licence, u.rc_book AS owner_rc
    FROM cars c JOIN users u ON u.id = c.owner_id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$car = $stmt->fetch();

if (!$car) { setFlash('error', 'Car not found.'); header('Location: /rentx/admin/cars.php'); exit; }

$pageTitle = 'Review Car Request';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-container" style="max-width:860px">
  <a href="/rentx/admin/cars.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← Back to Cars</a>
  <div class="section-title mb-3">Review Car Submission</div>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:2rem;align-items:start">
    <div>
      <!-- Car Details -->
      <div class="card">
        <div class="flex justify-between items-center mb-2">
          <h3><?= s($car['brand']) ?> <?= s($car['model']) ?> (<?= s($car['year']) ?>)</h3>
          <span class="status status-<?= $car['status'] ?>"><?= ucfirst($car['status']) ?></span>
        </div>
        <hr class="divider">
        <div class="form-grid">
          <div><div class="text-xs text-muted">Category</div><?= ucfirst($car['category']) ?></div>
          <div><div class="text-xs text-muted">Plate</div><?= s($car['plate']) ?></div>
          <div><div class="text-xs text-muted">Color</div><?= s($car['color'] ?: '—') ?></div>
          <div><div class="text-xs text-muted">Seats</div><?= $car['seats'] ?></div>
          <div><div class="text-xs text-muted">Fuel</div><?= ucfirst($car['fuel']) ?></div>
          <div><div class="text-xs text-muted">Transmission</div><?= ucfirst($car['transmission']) ?></div>
          <div><div class="text-xs text-muted">Price / Day</div><span class="text-gold"><?= formatINR($car['price_day']) ?></span></div>
        </div>
        <?php if ($car['description']): ?>
          <div class="mt-2"><div class="text-xs text-muted">Description</div><p class="text-sm mt-1"><?= nl2br(s($car['description'])) ?></p></div>
        <?php endif; ?>
      </div>

      <!-- Owner Details + Documents -->
      <div class="card mt-3" style="border-left:3px solid var(--gold)">
        <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">🚗 Owner Details & Verification Documents</div>
        <div class="form-grid mb-3">
          <div><div class="text-xs text-muted">Owner Name</div><strong><?= s($car['owner_name']) ?></strong></div>
          <div><div class="text-xs text-muted">Phone</div><strong><?= s($car['owner_phone'] ?: '—') ?></strong></div>
          <div><div class="text-xs text-muted">Email</div><?= s($car['owner_email']) ?></div>
        </div>

        <div class="form-grid">
          <div>
            <div class="text-xs text-muted mb-1">Owner Driving Licence</div>
            <?php if ($car['owner_licence']): ?>
              <?php $ext = strtolower(pathinfo($car['owner_licence'], PATHINFO_EXTENSION)); ?>
              <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                <img src="/rentx/<?= s($car['owner_licence']) ?>" style="max-width:100%;border-radius:8px;border:1px solid var(--border)">
              <?php else: ?>
                <a href="/rentx/<?= s($car['owner_licence']) ?>" target="_blank" class="btn btn-secondary btn-sm">📄 View Licence</a>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-error" style="padding:.5rem .8rem;font-size:.82rem">⚠ No driving licence uploaded</div>
            <?php endif; ?>
          </div>
          <div>
            <div class="text-xs text-muted mb-1">RC Book (Vehicle Registration)</div>
            <?php if ($car['owner_rc']): ?>
              <?php $ext = strtolower(pathinfo($car['owner_rc'], PATHINFO_EXTENSION)); ?>
              <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                <img src="/rentx/<?= s($car['owner_rc']) ?>" style="max-width:100%;border-radius:8px;border:1px solid var(--border)">
              <?php else: ?>
                <a href="/rentx/<?= s($car['owner_rc']) ?>" target="_blank" class="btn btn-secondary btn-sm">📄 View RC Book</a>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-error" style="padding:.5rem .8rem;font-size:.82rem">⚠ No RC Book uploaded</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Car Image + Actions -->
    <div>
      <?php if ($car['image']): ?>
        <div class="card" style="padding:.5rem">
          <img src="/rentx/<?= s($car['image']) ?>" style="width:100%;border-radius:8px;height:180px;object-fit:cover">
        </div>
      <?php endif; ?>

      <div class="card mt-3">
        <div class="text-sm text-muted mb-3">Review the owner's documents and car details before making a decision.</div>
        <div style="display:flex;flex-direction:column;gap:.75rem">
          <a href="/rentx/admin/car_approve.php?id=<?= $id ?>&action=approve"
             class="btn btn-success"
             style="justify-content:center"
             onclick="return confirm('Approve this car and list it?')">
            ✓ Approve &amp; List Car
          </a>
          <a href="/rentx/admin/car_approve.php?id=<?= $id ?>&action=reject"
             class="btn btn-danger"
             style="justify-content:center"
             onclick="return confirm('Reject this car submission?')">
            ✗ Reject Submission
          </a>
          <a href="/rentx/admin/cars.php" class="btn btn-secondary" style="justify-content:center">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
