<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'user');
$pageTitle = 'Book a Car';

$db     = getDB();
$carId  = (int)($_GET['car_id'] ?? 0);
$uid    = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT * FROM cars WHERE id = ? AND status = 'available'");
$stmt->execute([$carId]);
$car  = $stmt->fetch();
if (!$car) { setFlash('error', 'Car not available.'); header('Location: /rentx/cars.php'); exit; }

// Fetch user details (including driving licence)
$userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$uid]);
$userInfo = $userStmt->fetch();

// Fetch owner details
$ownerStmt = $db->prepare("SELECT name, phone, email FROM users WHERE id = ?");
$ownerStmt->execute([$car['owner_id']]);
$owner = $ownerStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start   = $_POST['start_date'] ?? '';
    $end     = $_POST['end_date']   ?? '';
    $pickup  = trim($_POST['pickup_loc']  ?? '');
    $dropoff = trim($_POST['dropoff_loc'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');

    // Handle driving licence upload (optional override, use existing if already uploaded)
    $licence_path = $userInfo['driving_licence'] ?? null;
    if (!empty($_FILES['driving_licence']['name'])) {
        $uploaded = uploadImage($_FILES['driving_licence'], 'uploads/licences/');
        if ($uploaded) {
            $licence_path = $uploaded;
            // Update user's licence on file
            $db->prepare("UPDATE users SET driving_licence=? WHERE id=?")->execute([$licence_path, $uid]);
        }
    }

    if (!$licence_path) {
        $error = 'A valid driving licence must be on file. Please upload yours.';
    } else {
        $startDt = new DateTime($start);
        $endDt   = new DateTime($end);
        $days    = max(1, $endDt->diff($startDt)->days);
        $total   = $days * $car['price_day'];

        if ($endDt <= $startDt) {
            $error = 'End date must be after start date.';
        } else {
            $overlap = $db->prepare("
                SELECT id FROM bookings WHERE car_id=? AND status IN ('confirmed','active')
                AND NOT (end_date <= ? OR start_date >= ?)
            ");
            $overlap->execute([$carId, $start, $end]);
            if ($overlap->fetch()) {
                $error = 'Car is already booked for those dates. Please pick different dates.';
            } else {
                $ins = $db->prepare("
                    INSERT INTO bookings (user_id,car_id,start_date,end_date,total_days,total_price,pickup_loc,dropoff_loc,notes,status)
                    VALUES (?,?,?,?,?,?,?,?,?,'pending')
                ");
                $ins->execute([$uid, $carId, $start, $end, $days, $total, $pickup, $dropoff, $notes]);
                $bookingId = $db->lastInsertId();

                notifyAllAdmins('New Booking Request', "User #{$uid} booked {$car['brand']} {$car['model']} from {$start} to {$end}.");
                addNotif($uid, 'Booking Submitted', "Your booking for {$car['brand']} {$car['model']} is pending confirmation.");

                setFlash('success', 'Booking submitted! Awaiting admin confirmation.');
                header('Location: /rentx/user/my_bookings.php'); exit;
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container">
  <a href="/rentx/cars.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← Back to Cars</a>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start">
    <!-- Booking Form -->
    <div>
      <div class="section-title mb-2">Book Your Car</div>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= s($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="card">
        <input type="hidden" id="price_per_day" value="<?= $car['price_day'] ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Pickup Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Return Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control" required>
          </div>
        </div>
        <div class="form-grid mt-2">
          <div class="form-group">
            <label>Pickup Location</label>
            <input type="text" name="pickup_loc" class="form-control" placeholder="e.g. Airport Terminal 1">
          </div>
          <div class="form-group">
            <label>Drop-off Location</label>
            <input type="text" name="dropoff_loc" class="form-control" placeholder="e.g. City Center">
          </div>
        </div>
        <div class="form-group mt-2">
          <label>Special Notes</label>
          <textarea name="notes" class="form-control" placeholder="Any special requirements..."></textarea>
        </div>

        <!-- Driving Licence -->
        <div class="form-group mt-2">
          <?php if (!empty($userInfo['driving_licence'])): ?>
            <div class="alert alert-success" style="padding:.6rem .9rem;font-size:.85rem">
              ✅ Driving licence already on file.
              <a href="/rentx/<?= s($userInfo['driving_licence']) ?>" target="_blank" class="text-gold" style="margin-left:.5rem">View</a>
              &nbsp;|&nbsp; <label style="cursor:pointer;color:var(--gold)">
                Update &nbsp;<input type="file" name="driving_licence" accept="image/*,.pdf" style="display:none">
              </label>
            </div>
          <?php else: ?>
            <label>Driving Licence * <span class="text-muted text-xs">(Required)</span></label>
            <input type="file" name="driving_licence" class="form-control" accept="image/*,.pdf" required>
            <div class="text-xs text-muted mt-1">Your licence will be verified by the admin before booking is confirmed.</div>
          <?php endif; ?>
        </div>

        <!-- Summary -->
        <div class="card mt-3" style="background:var(--bg3)">
          <div class="flex justify-between text-sm mb-1">
            <span class="text-muted">Days</span>
            <strong id="total_days_display">—</strong>
          </div>
          <div class="flex justify-between text-sm mb-1">
            <span class="text-muted">Rate</span>
            <strong><?= formatINR($car['price_day']) ?>/day</strong>
          </div>
          <hr class="divider">
          <div class="flex justify-between">
            <span class="text-muted">Total Estimate</span>
            <strong class="text-gold" style="font-size:1.3rem" id="total_display"><?= formatINR(0) ?></strong>
          </div>
          <input type="hidden" name="total_days"  id="total_days">
          <input type="hidden" name="total_price" id="total_price">
        </div>

        <div class="alert alert-info mt-3">
          Your booking will be reviewed by the admin. You'll be notified once confirmed.
        </div>
        <button type="submit" class="btn btn-primary mt-2 w-full" style="justify-content:center">Submit Booking Request</button>
      </form>
    </div>

    <!-- Car + Owner Summary -->
    <div>
      <div class="card">
        <?php if ($car['image']): ?>
          <img src="/rentx/<?= s($car['image']) ?>" style="width:100%;border-radius:8px;margin-bottom:1rem;height:180px;object-fit:cover">
        <?php endif; ?>
        <div style="font-family:'Bebas Neue',sans-serif;font-size:1.4rem"><?= s($car['brand']) ?> <?= s($car['model']) ?></div>
        <div class="text-muted text-sm"><?= s($car['year']) ?> · <?= ucfirst(s($car['category'])) ?></div>
        <hr class="divider">
        <div class="text-sm" style="display:flex;flex-direction:column;gap:.5rem">
          <div class="flex justify-between"><span class="text-muted">Seats</span><span><?= $car['seats'] ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Fuel</span><span><?= ucfirst($car['fuel']) ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Gearbox</span><span><?= ucfirst($car['transmission']) ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Plate</span><span><?= s($car['plate']) ?></span></div>
        </div>
        <hr class="divider">
        <div class="text-gold" style="font-size:1.6rem;font-family:'Bebas Neue',sans-serif"><?= formatINR($car['price_day']) ?> <span style="font-size:.9rem;color:var(--text2)">/day</span></div>
      </div>

      <!-- Your Details -->
      <div class="card mt-3">
        <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">Your Details</div>
        <div class="text-sm" style="display:flex;flex-direction:column;gap:.45rem">
          <div class="flex justify-between"><span class="text-muted">Name</span><span><?= s($userInfo['name']) ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Phone</span><span><?= s($userInfo['phone'] ?: '—') ?></span></div>
          <div class="flex justify-between"><span class="text-muted">Email</span><span><?= s($userInfo['email']) ?></span></div>
        </div>
        <a href="/rentx/profile.php" class="text-xs text-gold mt-2" style="display:inline-block">Edit Profile →</a>
      </div>
    </div>
  </div>
</div>

<script>
const pricePerDay = <?= $car['price_day'] ?>;
const sd = document.getElementById('start_date');
const ed = document.getElementById('end_date');
function recalc() {
  if (!sd.value || !ed.value) return;
  const days = Math.ceil((new Date(ed.value) - new Date(sd.value)) / 86400000);
  if (days > 0) {
    document.getElementById('total_days').value        = days;
    document.getElementById('total_price').value       = (days * pricePerDay).toFixed(2);
    document.getElementById('total_days_display').textContent = days + ' day(s)';
    document.getElementById('total_display').textContent      = <?= formatINR('(days * pricePerDay).toFixed(2)') ?>;
  }
}
sd.addEventListener('change', recalc);
ed.addEventListener('change', recalc);
const today = new Date().toISOString().split('T')[0];
sd.min = today; ed.min = today;
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
