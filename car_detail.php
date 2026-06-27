<?php
require_once __DIR__ . '/includes/helpers.php';
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT c.*, u.name AS owner_name, u.phone AS owner_phone FROM cars c JOIN users u ON u.id = c.owner_id WHERE c.id = ?");
$stmt->execute([$id]);
$car = $stmt->fetch();
if (!$car) { header('Location: /rentx/cars.php'); exit; }
$pageTitle = $car['brand'] . ' ' . $car['model'];
include __DIR__ . '/includes/header.php';
?>
<div class="page-container">
  <a href="/rentx/cars.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← Back to Cars</a>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:2rem;align-items:start" class="mt-2">
    <!-- Left: Image + Info -->
    <div>
      <?php if ($car['image']): ?>
        <img src="/rentx/<?= s($car['image']) ?>" alt="" style="width:100%;border-radius:12px;max-height:380px;object-fit:cover">
      <?php else: ?>
        <div class="car-card-img-placeholder" style="border-radius:12px;height:320px;font-size:5rem">🚗</div>
      <?php endif; ?>

      <div class="card mt-3">
        <h2 class="text-gold" style="font-size:1.6rem"><?= s($car['brand']) ?> <?= s($car['model']) ?> (<?= s($car['year']) ?>)</h2>
        <div class="car-card-meta mt-2">
          <span class="tag tag-gold"><?= ucfirst(s($car['category'])) ?></span>
          <span class="tag"><?= s($car['seats']) ?> Seats</span>
          <span class="tag"><?= ucfirst(s($car['transmission'])) ?></span>
          <span class="tag"><?= ucfirst(s($car['fuel'])) ?></span>
          <?php if ($car['color']): ?><span class="tag"><?= s($car['color']) ?></span><?php endif; ?>
          <span class="status status-<?= s($car['status']) ?>"><?= ucfirst(s($car['status'])) ?></span>
        </div>
        <?php if ($car['description']): ?>
          <p class="text-muted mt-2" style="line-height:1.7"><?= nl2br(s($car['description'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Pricing + Book -->
    <div>
      <div class="card">
        <div class="text-muted text-sm">Daily Rate</div>
        <div style="font-family:'Bebas Neue',sans-serif;font-size:3rem;color:var(--gold)"><?= formatINR($car['price_day']) ?></div>
        <div class="text-muted text-xs">per day</div>
        <hr class="divider">
        <div class="text-sm" style="display:flex;flex-direction:column;gap:.5rem">
          <div class="flex justify-between"><span class="text-muted">Plate No.</span><strong><?= s($car['plate']) ?></strong></div>
          <div class="flex justify-between"><span class="text-muted">Owner</span><strong><?= s($car['owner_name']) ?></strong></div>
        </div>
        <hr class="divider">
        <?php if ($car['status'] === 'available'): ?>
          <?php if (isLoggedIn() && userRole() === 'user'): ?>
            <a href="/rentx/user/book.php?car_id=<?= $car['id'] ?>" class="btn btn-primary w-full" style="justify-content:center">Book This Car</a>
          <?php elseif (!isLoggedIn()): ?>
            <a href="/rentx/index.php#login" class="btn btn-primary w-full" style="justify-content:center">Login to Book</a>
          <?php else: ?>
            <p class="text-muted text-sm text-center">Logged in as <?= strtoupper(userRole()) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-info">This car is currently not available for booking.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
