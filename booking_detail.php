<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'user');
$pageTitle = 'Booking Details';

$db  = getDB();
$uid = $_SESSION['user_id'];
$bid = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT b.*, c.brand, c.model, c.image, c.plate, c.price_day, c.fuel, c.transmission,
           u.name AS owner_name, u.phone AS owner_phone, u.email AS owner_email
    FROM bookings b
    JOIN cars c     ON c.id = b.car_id
    JOIN users u    ON u.id = c.owner_id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$bid, $uid]);
$booking = $stmt->fetch();
if (!$booking) {
    setFlash('error', 'Booking not found.');
    header('Location: /rentx/user/my_bookings.php');
    exit;
}

$payment = $db->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1");
$payment->execute([$bid]);
$payment = $payment->fetch();

$userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$uid]);
$userInfo = $userStmt->fetch();

$hasPayment = $payment ? true : false;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <a href="/rentx/user/my_bookings.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← My Bookings</a>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:2rem;align-items:start">
    <div>
      <!-- Booking card -->
      <div class="card">
        <div class="flex justify-between items-center mb-2">
          <h2>Booking #<?= $booking['id'] ?></h2>
          <span class="status status-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span>
        </div>
        <hr class="divider">
        <div class="form-grid">
          <div><div class="text-xs text-muted">Car</div><div><?= s($booking['brand']) ?> <?= s($booking['model']) ?></div></div>
          <div><div class="text-xs text-muted">Plate</div><div><?= s($booking['plate']) ?></div></div>
          <div><div class="text-xs text-muted">Start Date</div><div><?= s($booking['start_date']) ?></div></div>
          <div><div class="text-xs text-muted">End Date</div><div><?= s($booking['end_date']) ?></div></div>
          <div><div class="text-xs text-muted">Duration</div><div><?= $booking['total_days'] ?> days</div></div>
          <div><div class="text-xs text-muted">Daily Rate</div><div><?= formatINR($booking['price_day']) ?></div></div>
          <div><div class="text-xs text-muted">Pickup Location</div><div><?= s($booking['pickup_loc'] ?: '—') ?></div></div>
          <div><div class="text-xs text-muted">Drop-off Location</div><div><?= s($booking['dropoff_loc'] ?: '—') ?></div></div>
        </div>
        <?php if ($booking['notes']): ?>
          <div class="mt-2 text-muted text-sm"><?= nl2br(s($booking['notes'])) ?></div>
        <?php endif; ?>
        <hr class="divider">
        <div class="flex justify-between">
          <span class="text-muted">Total Amount</span>
          <strong class="text-gold" style="font-size:1.5rem"><?= formatINR($booking['total_price']) ?></strong>
        </div>
      </div>

      <!-- Owner Contact -->
      <?php if (in_array($booking['status'], ['confirmed','active','completed'])): ?>
      <div class="card mt-3" style="border-left:3px solid var(--gold)">
        <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">🚗 Car Owner Contact</div>
        <div class="form-grid">
          <div><div class="text-xs text-muted">Owner Name</div><strong><?= s($booking['owner_name']) ?></strong></div>
          <div><div class="text-xs text-muted">Phone</div><strong><?= s($booking['owner_phone'] ?: '—') ?></strong></div>
          <div><div class="text-xs text-muted">Email</div><span><?= s($booking['owner_email']) ?></span></div>
          <div><div class="text-xs text-muted">Pickup Location</div><span><?= s($booking['pickup_loc'] ?: 'To be confirmed') ?></span></div>
        </div>
        <?php if ($booking['status'] === 'confirmed'): ?>
          <div class="alert alert-info mt-2" style="padding:.6rem .9rem;font-size:.85rem">
            📞 Please contact the owner to arrange pickup at the confirmed location.
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Payment Info -->
      <div class="card mt-3">
        <h3>Payment</h3>
        <hr class="divider">

        <?php if ($payment): ?>
          <div class="form-grid">
            <div><div class="text-xs text-muted">Amount</div><div class="text-gold"><?= formatINR($payment['amount']) ?></div></div>
            <div>
              <div class="text-xs text-muted">Method</div>
              <div>
                <?php
                  $method_label = [
                      'upi_qr'       => '📱 UPI / QR',
                      'cash'         => '💵 Cash on Delivery',
                      'bank_transfer'=> '🏦 Bank Transfer',
                      'online'       => '🌐 Online',
                  ];
                  echo $method_label[$payment['method']] ?? ucfirst(str_replace('_',' ',$payment['method']));
                ?>
              </div>
            </div>

            <?php if (!empty($payment['reference'])): ?>
            <div>
              <div class="text-xs text-muted">UTR / Transaction ID</div>
              <div style="font-family:monospace;background:var(--bg3);padding:.3rem .6rem;border-radius:6px;display:inline-block">
                <?= s($payment['reference']) ?>
              </div>
            </div>
            <?php endif; ?>

            <div>
              <div class="text-xs text-muted">Status</div>
              <span class="status status-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></span>
            </div>
          </div>

          <!-- Receipt screenshot -->
          <?php if (!empty($payment['receipt'])): ?>
          <div class="mt-3">
            <div class="text-xs text-muted mb-1">Payment Screenshot</div>
            <a href="/rentx/<?= s($payment['receipt']) ?>" target="_blank">
              <img src="/rentx/<?= s($payment['receipt']) ?>"
                   style="max-width:180px;border-radius:8px;border:1px solid var(--border);display:block">
            </a>
            <div class="text-xs text-muted mt-1">Click to open full size</div>
          </div>
          <?php endif; ?>

          <?php if ($payment['status'] === 'pending'): ?>
            <div class="alert alert-info mt-3" style="font-size:.85rem">
              ⏳ Your payment is under review. Admin will confirm shortly.
            </div>
          <?php elseif ($payment['status'] === 'confirmed'): ?>
            <div class="alert alert-success mt-3" style="font-size:.85rem">
              ✅ Payment confirmed! Please contact the owner to coordinate pickup.
            </div>
          <?php elseif ($payment['status'] === 'released'): ?>
            <div class="alert alert-success mt-3" style="font-size:.85rem">
              💸 Payment has been released to the owner. Booking complete!
            </div>
          <?php elseif ($payment['status'] === 'refunded'): ?>
            <div class="alert alert-warning mt-3" style="font-size:.85rem">
              ↩ Your payment has been refunded. Contact support for details.
            </div>
          <?php elseif ($payment['status'] === 'failed'): ?>
            <div class="alert alert-error mt-3" style="font-size:.85rem">
              ❌ Payment verification failed. Please contact support.
            </div>
          <?php endif; ?>

        <?php else: ?>
          <p class="text-muted text-sm">No payment record found.</p>
        <?php endif; ?>

        <!-- Submit payment button (only if booking confirmed and no payment yet) -->
        <?php if ($booking['status'] === 'confirmed' && !$hasPayment): ?>
          <a href="/rentx/user/payment.php?booking_id=<?= $bid ?>" class="btn btn-success mt-3">
            📱 Submit Payment
          </a>
        <?php endif; ?>
      </div>

      <!-- Delivery / Return actions -->
      <?php if ($booking['status'] === 'confirmed' && $hasPayment && $payment['status'] === 'confirmed'): ?>
        <div class="card mt-3">
          <h3>Delivery &amp; Return</h3>
          <hr class="divider">

          <?php if (!$booking['user_confirmed']): ?>
            <a href="/rentx/user/confirm_delivery.php?id=<?= $booking['id'] ?>&type=delivery"
               class="btn btn-success">Confirm Delivery</a>

          <?php elseif ($booking['user_confirmed'] && !$booking['user_return']): ?>
            <div class="alert alert-success mb-2" style="font-size:.85rem">✅ Delivery confirmed.</div>
            <a href="/rentx/user/confirm_delivery.php?id=<?= $booking['id'] ?>&type=return"
               class="btn btn-primary">Return Car</a>

          <?php else: ?>
            <span class="text-muted">✔ Return confirmed. Process complete.</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div>
      <div class="card">
        <?php if ($booking['image']): ?>
          <img src="/rentx/<?= s($booking['image']) ?>"
               style="width:100%;border-radius:8px;margin-bottom:1rem;height:160px;object-fit:cover">
        <?php endif; ?>
        <div style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem"><?= s($booking['brand']) ?> <?= s($booking['model']) ?></div>
        <div class="text-muted text-sm mt-1">Owner: <?= s($booking['owner_name']) ?></div>
        <div class="text-sm mt-2 text-muted">
          <?php if ($booking['status'] === 'pending'): ?>
            ⏳ Awaiting admin confirmation.
          <?php elseif ($booking['status'] === 'confirmed'): ?>
            ✅ Booking confirmed! Please complete payment and contact the owner.
          <?php elseif ($booking['status'] === 'active'): ?>
            🚗 Your rental is currently active.
          <?php elseif ($booking['status'] === 'completed'): ?>
            🏁 Rental completed. Thank you!
          <?php elseif ($booking['status'] === 'cancelled'): ?>
            ❌ This booking was cancelled.
          <?php endif; ?>
        </div>
      </div>

      <!-- Your Details -->
      <div class="card mt-3">
        <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">Your Details</div>
        <div class="text-sm" style="display:flex;flex-direction:column;gap:.45rem">
          <div class="flex justify-between"><span class="text-muted">Name</span><span><?= s($userInfo['name']) ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Phone</span><span><?= s($userInfo['phone'] ?: '—') ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Email</span><span><?= s($userInfo['email']) ?></span></div>
          <?php if (!empty($userInfo['driving_licence'])): ?>
          <div class="flex justify-between">
            <span class="text-muted">Driving Licence</span>
            <a href="/rentx/<?= s($userInfo['driving_licence']) ?>" target="_blank" class="text-gold text-xs">View</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>