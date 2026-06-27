<?php
// Owner view of a specific booking on their car
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'owner');
$pageTitle = 'Bookings';

$db  = getDB();
$uid = $_SESSION['user_id'];
$bid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID → show all bookings list
if ($bid == 0) {

    $stmt = $db->prepare("
        SELECT b.id, c.brand, c.model, b.start_date, b.end_date, b.status, b.total_price
        FROM bookings b
        JOIN cars c ON c.id = b.car_id
        WHERE c.owner_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$uid]);
    $bookings = $stmt->fetchAll();

    include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
    <h2>My Bookings</h2>

    <?php foreach ($bookings as $b): ?>
        <div class="card mt-2">
            <strong>#<?= $b['id'] ?> - <?= $b['brand'] ?> <?= $b['model'] ?></strong><br>
            <?= $b['start_date'] ?> → <?= $b['end_date'] ?><br>
            Status: <?= $b['status'] ?><br><br>

            <a href="?id=<?= $b['id'] ?>" class="btn">View Details</a>
        </div>
    <?php endforeach; ?>

</div>
<?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Verify this booking is for one of the owner's cars
$stmt = $db->prepare("
    SELECT b.*,b.owner_confirmed,b.user_confirmed,b.owner_return,b.user_return,
    c.brand, c.model, c.image, c.plate, c.price_day, c.fuel, c.transmission, c.seats,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           u.driving_licence AS user_licence,
           b.payment_status AS payment_status, p.amount AS paid_amount
    FROM bookings b
    JOIN cars c    ON c.id  = b.car_id
    JOIN users u   ON u.id  = b.user_id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.id = ? AND c.owner_id = ?
");
$stmt->execute([$bid, $uid]);
$booking = $stmt->fetch();
if (!$booking) { setFlash('error', 'Booking not found.'); header('Location: /rentx/owner/dashboard.php'); exit; }

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <a href="/rentx/owner/bookings.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← My Car Bookings</a>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:2rem;align-items:start">
    <div>
      <!-- Booking Summary -->
      <div class="card">
        <div class="flex justify-between items-center mb-2">
          <h2>Booking #<?= $bid ?></h2>
          <span class="status status-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span>
        </div>
        <hr class="divider">
        <div class="form-grid">
          <div><div class="text-xs text-muted">Car</div><strong><?= s($booking['brand']) ?> <?= s($booking['model']) ?></strong></div>
          <div><div class="text-xs text-muted">Plate</div><?= s($booking['plate']) ?></div>
          <div><div class="text-xs text-muted">Start Date</div><?= s($booking['start_date']) ?></div>
          <div><div class="text-xs text-muted">End Date</div><?= s($booking['end_date']) ?></div>
          <div><div class="text-xs text-muted">Duration</div><?= $booking['total_days'] ?> days</div>
          <div><div class="text-xs text-muted">Daily Rate</div><?= formatINR($booking['price_day']) ?></div>
        </div>
        <hr class="divider">
        <div class="flex justify-between">
          <span class="text-muted">Total Amount</span>
          <strong class="text-gold" style="font-size:1.5rem"><?= formatINR($booking['total_price']) ?></strong>
        </div>
      </div>

      <!-- Renter Details — shown after confirmed/active/completed -->
      <?php if (in_array($booking['status'], ['confirmed','active','completed'])): ?>
      <div class="card mt-3" style="border-left:3px solid #4a9eff">
        <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">🙋 Renter Contact & Details</div>
        <div class="form-grid">
          <div><div class="text-xs text-muted">Full Name</div><strong><?= s($booking['user_name']) ?></strong></div>
          <div><div class="text-xs text-muted">Phone Number</div><strong><?= s($booking['user_phone'] ?: '—') ?></strong></div>
          <div><div class="text-xs text-muted">Email</div><?= s($booking['user_email']) ?></div>
        </div>

        <!-- Pickup / Delivery Details -->
        <hr class="divider">
        <div class="form-grid">
          <div>
            <div class="text-xs text-muted">Pickup Location</div>
            <strong><?= s($booking['pickup_loc'] ?: '—') ?></strong>
          </div>
          <div>
            <div class="text-xs text-muted">Drop-off Location</div>
            <strong><?= s($booking['dropoff_loc'] ?: '—') ?></strong>
          </div>
          <div>
            <div class="text-xs text-muted">Pickup Date</div>
            <strong><?= s($booking['start_date']) ?></strong>
          </div>
          <div>
            <div class="text-xs text-muted">Return Date</div>
            <strong><?= s($booking['end_date']) ?></strong>
          </div>
        </div>

        <?php if ($booking['notes']): ?>
          <div class="mt-2">
            <div class="text-xs text-muted">Renter Notes</div>
            <p class="text-sm mt-1"><?= nl2br(s($booking['notes'])) ?></p>
          </div>
        <?php endif; ?>

        <!-- Driving Licence -->
        <hr class="divider">
        <div class="text-xs text-muted mb-1">Renter's Driving Licence</div>
        <?php if ($booking['user_licence']): ?>
          <?php $ext = strtolower(pathinfo($booking['user_licence'], PATHINFO_EXTENSION)); ?>
          <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
            <img src="/rentx/<?= s($booking['user_licence']) ?>" style="max-width:280px;border-radius:8px;border:1px solid var(--border)">
          <?php else: ?>
            <a href="/rentx/<?= s($booking['user_licence']) ?>" target="_blank" class="btn btn-secondary btn-sm">📄 View Licence</a>
          <?php endif; ?>
        <?php else: ?>
          <span class="text-muted text-sm">No driving licence on file.</span>
        <?php endif; ?>

        <?php if ($booking['status'] === 'confirmed'): ?>
          <div class="alert alert-info mt-2" style="padding:.6rem .9rem;font-size:.85rem">
            📞 Contact the renter to coordinate pickup at <strong><?= s($booking['pickup_loc'] ?: 'the agreed location') ?></strong>.
          </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="card mt-3">
        <div class="alert alert-info" style="padding:.7rem 1rem;font-size:.88rem">
          ℹ️ Renter contact details and driving licence will be visible once the admin confirms this booking.
        </div>
      </div>
      <?php endif; ?>

      <!-- Payment -->
      <div class="card mt-3">
  <h3>Payment Status</h3>

  <?php if ($booking['payment_status'] == 'unpaid'): ?>
    <span style="color:red;">Not Paid</span>

  <?php elseif ($booking['payment_status'] == 'pending'): ?>
    <span style="color:orange;">Waiting for Admin</span>

  <?php elseif ($booking['payment_status'] == 'confirmed'): ?>
    <span style="color:orange;">Paid (Holding)</span>

  <?php elseif ($booking['payment_status'] == 'released'): ?>
    <span style="color:green;">Payment Released ✅</span>

  <?php endif; ?>

  <div class="mt-2">
    Amount: <strong><?= formatINR($booking['total_price']) ?></strong>
  </div>
</div>


<div class="card mt-2">
  <h4>Delivery Status</h4>

 <?php if ($booking['owner_confirmed'] && $booking['user_confirmed']): ?>

    <span style="color:green;">Delivered ✅</span>

<?php else: ?>

    <span style="color:orange;">Waiting for Delivery</span>

<?php endif; ?>
 
  <?php if (in_array($booking['status'] ,['confirmed','active'])): ?>

    <?php if (!$booking['owner_confirmed']): ?>

        <a href="/rentx/owner/confirm_delivery.php?id=<?= $booking['id'] ?>&type=delivery"
           class="btn btn-success mt-2">
           Mark Delivered
        </a>

    <?php elseif ($booking['user_return'] && !$booking['owner_return']): ?>

        <a href="/rentx/owner/confirm_delivery.php?id=<?= $booking['id'] ?>&type=return"
           class="btn btn-primary mt-2">
           Confirm Return
        </a>

    <?php elseif ($booking['owner_return']): ?>

        <span class="text-muted">✔ Completed</span>

    <?php endif; ?>

<?php endif; ?>
</div>



    <!-- Car Image Sidebar -->
    <div>
      <div class="card">
        <?php if ($booking['image']): ?>
          <img src="/rentx/<?= s($booking['image']) ?>" style="width:100%;border-radius:8px;margin-bottom:1rem;height:160px;object-fit:cover">
        <?php endif; ?>
        <div style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem"><?= s($booking['brand']) ?> <?= s($booking['model']) ?></div>
        <hr class="divider">
        <div class="text-sm" style="display:flex;flex-direction:column;gap:.5rem">
          <div class="flex justify-between"><span class="text-muted">Seats</span><span><?= $booking['seats'] ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Fuel</span><span><?= ucfirst($booking['fuel']) ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Gearbox</span><span><?= ucfirst($booking['transmission']) ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Plate</span><span><?= s($booking['plate']) ?></span></div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">Status Guide</div>
        <div class="text-xs text-muted" style="display:flex;flex-direction:column;gap:.4rem;line-height:1.6">
          <div>⏳ <strong>Pending</strong> — Awaiting admin review</div>
          <div>✅ <strong>Confirmed</strong> — You can contact the renter</div>
          <div>🚗 <strong>Active</strong> — Car is currently with renter</div>
          <div>🏁 <strong>Completed</strong> — Car has been returned</div>
          <div>❌ <strong>Cancelled</strong> — Booking was cancelled</div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
