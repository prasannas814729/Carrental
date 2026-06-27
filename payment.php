<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'user');
$pageTitle = 'Submit Payment';

$db  = getDB();
$uid = $_SESSION['user_id'];
$bid = (int)($_GET['booking_id'] ?? 0);

$stmt = $db->prepare("SELECT b.*, c.brand, c.model FROM bookings b JOIN cars c ON c.id=b.car_id WHERE b.id=? AND b.user_id=? AND b.status='confirmed'");
$stmt->execute([$bid, $uid]);
$booking = $stmt->fetch();
if (!$booking) { setFlash('error', 'Booking not available.'); header('Location: /rentx/user/my_bookings.php'); exit; }

$pay = $db->prepare("SELECT * FROM payments WHERE booking_id=?");
$pay->execute([$bid]);
$payment = $pay->fetch();
if ($payment) { setFlash('error', 'Payment already submitted.'); header('Location: /rentx/user/booking_detail.php?id=' . $bid); exit; }

// ── Razorpay dynamic QR code ──────────────────────────────────────────────────
// Replace these with your actual Razorpay key & secret
define('RAZORPAY_KEY_ID',     'rzp_live_XXXXXXXXXXXXXXX');
define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');

$qr_image_url  = null;
$qr_error      = null;
$amount_paise  = (int)($booking['total_price'] * 100); // Razorpay works in paise

try {
    $rz_payload = json_encode([
        "type"        => "upi_qr",
        "name"        => "RentX Payment - Booking #" . $bid,
        "usage"       => "single_use",
        "fixed_amount"=> true,
        "payment_amount" => $amount_paise,
        "description" => "Car rental booking #" . $bid,
        "close_by"    => time() + 86400, // expires in 24 hours
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/payments/qr_codes');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $rz_payload,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $rz = json_decode($response, true);
        $qr_image_url = $rz['image_url'] ?? null;
    } else {
        $qr_error = "Could not generate QR. Please use the static QR or pay by cash.";
    }
} catch (Exception $e) {
    $qr_error = "QR generation failed. Please pay by cash.";
}

// ── Handle form POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method    = $_POST['method'] ?? 'upi_qr';
    $utr       = trim($_POST['utr'] ?? '');
    $receipt   = null;

    // Validate
    $errors = [];
    if ($method === 'upi_qr' && empty($utr)) {
        $errors[] = 'Please enter the UTR / Transaction ID.';
    }
    if ($method === 'upi_qr' && empty($_FILES['receipt']['name'])) {
        $errors[] = 'Please upload a screenshot of the payment.';
    }

    if (empty($errors)) {
        if (!empty($_FILES['receipt']['name'])) {
            $receipt = uploadImage($_FILES['receipt'], 'uploads/receipts/');
        }

        $db->prepare("INSERT INTO payments 
            (booking_id, user_id, amount, method, reference, receipt, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())")
           ->execute([
               $bid,
               $uid,
               $booking['total_price'],
               $method,
               $utr,
               $receipt
           ]);

        notifyAllAdmins('Payment Submitted', "User submitted payment for booking #{$bid}. Please verify.");
        addNotif($uid, 'Payment Submitted', "Your payment details for booking #{$bid} are under review.");

        setFlash('success', 'Payment submitted! Admin will verify and confirm shortly.');
        header('Location: /rentx/user/booking_detail.php?id=' . $bid);
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<style>
/* ── Tab toggle using pure CSS radio trick ── */
#radio-upi_qr, #radio-cash { display:none !important; }

.payment-tabs { display:flex; gap:.5rem; margin-bottom:1rem; }
.pay-tab {
    flex:1; padding:.85rem .5rem; border:2px solid var(--border,#ccc);
    border-radius:10px; cursor:pointer; text-align:center;
    background:var(--bg2,#f5f5f5); transition:border-color .2s, background .2s;
    user-select:none;
}
.pay-tab .tab-icon { font-size:1.8rem; display:block; margin-bottom:.3rem; pointer-events:none; }
.pay-tab .tab-label { font-size:.85rem; font-weight:700; pointer-events:none; }

/* When UPI radio is checked → highlight UPI tab, show UPI panel */
#radio-upi_qr:checked ~ .payment-tabs #tab-upi_qr {
    border-color:#d4af37; background:rgba(212,175,55,.12);
}
#radio-upi_qr:checked ~ #panel-upi_qr { display:block !important; }
#radio-upi_qr:checked ~ #panel-cash   { display:none  !important; }

/* When Cash radio is checked → highlight Cash tab, show Cash panel */
#radio-cash:checked ~ .payment-tabs #tab-cash {
    border-color:#d4af37; background:rgba(212,175,55,.12);
}
#radio-cash:checked ~ #panel-cash   { display:block !important; }
#radio-cash:checked ~ #panel-upi_qr { display:none  !important; }

/* Default: UPI shown, Cash hidden */
#panel-upi_qr { display:block; }
#panel-cash   { display:none;  }

.qr-box {
    text-align:center; padding:1.5rem;
    background:var(--bg3,#eee); border-radius:12px;
    border:1px solid var(--border,#ccc); margin-bottom:1rem;
}
.qr-box img { width:220px; height:220px; border-radius:8px; border:4px solid #fff; }
.amount-badge {
    display:inline-block; background:#d4af37; color:#000;
    font-weight:700; font-size:1.1rem; padding:.4rem 1.2rem;
    border-radius:20px; margin:.75rem 0 .5rem;
}
.upi-id-box {
    background:var(--bg2,#f5f5f5); border:1px dashed var(--border,#ccc);
    border-radius:8px; padding:.6rem 1rem; font-size:.85rem;
    display:flex; align-items:center; justify-content:space-between; margin-top:.5rem;
}
.steps { list-style:none; padding:0; margin:.75rem 0 0; }
.steps li { padding:.35rem 0; font-size:.85rem; color:var(--text-muted,#777); }
.steps li::before { content:"→ "; color:#d4af37; font-weight:700; }
</style>

<div class="page-container" style="max-width:580px">
  <a href="/rentx/user/booking_detail.php?id=<?= $bid ?>" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← Back to Booking</a>

  <div class="section-title mb-2">Submit Payment</div>

  <!-- Amount summary -->
  <div class="card mb-3" style="background:var(--bg3)">
    <div class="flex justify-between text-sm">
      <span class="text-muted">Booking</span>
      <strong>#<?= $bid ?> — <?= s($booking['brand']) ?> <?= s($booking['model']) ?></strong>
    </div>
    <div class="flex justify-between text-sm mt-1">
      <span class="text-muted">Amount Due</span>
      <strong class="text-gold" style="font-size:1.25rem"><?= formatINR($booking['total_price']) ?></strong>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-2">
      <?php foreach ($errors as $e): ?><div>⚠ <?= s($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="card" id="payForm">

    <!-- Method tabs — pure CSS toggle, no JS needed -->
    <div class="text-xs text-muted mb-1" style="text-transform:uppercase;letter-spacing:.08em">Select Payment Method</div>

    <!-- Radios MUST be siblings BEFORE tabs and panels for CSS ~ selector -->
    <input type="radio" name="method" id="radio-upi_qr" value="upi_qr" checked>
    <input type="radio" name="method" id="radio-cash"   value="cash">

    <div class="payment-tabs">
      <label class="pay-tab" id="tab-upi_qr" for="radio-upi_qr">
        <span class="tab-icon">📱</span>
        <span class="tab-label">UPI / QR Code</span>
      </label>
      <label class="pay-tab" id="tab-cash" for="radio-cash">
        <span class="tab-icon">💵</span>
        <span class="tab-label">Cash on Delivery</span>
      </label>
    </div>

    <!-- ── UPI Panel ── -->
    <div id="panel-upi_qr">

      <div class="qr-box">
        <?php if ($qr_image_url): ?>
          <img src="<?= htmlspecialchars($qr_image_url) ?>" alt="Payment QR Code">
        <?php else: ?>
          <!-- Fallback: static QR image — replace src with your PhonePe/GPay static QR -->
          <img src="/rentx/uploads/payment_qr.jpeg" alt="Static QR Code">
          <?php if ($qr_error): ?>
            <div class="text-xs text-muted mt-1"><?= s($qr_error) ?></div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="amount-badge"><?= formatINR($booking['total_price']) ?></div>
        <div class="text-xs text-muted">Scan & pay exactly this amount</div>

        <div class="upi-id-box mt-2">
          <span>UPI ID: <strong id="upiIdText">rentx@upi</strong></span>
          <button type="button" onclick="copyUpi()" style="background:none;border:none;cursor:pointer;color:var(--gold);font-size:.8rem">Copy</button>
        </div>
      </div>

      <ul class="steps">
        <li>Open any UPI app (PhonePe, GPay, Paytm, BHIM)</li>
        <li>Scan the QR — the amount is pre-filled automatically</li>
        <li>Complete the payment and note the <strong>UTR / Transaction ID</strong></li>
        <li>Enter the UTR and upload a screenshot below</li>
      </ul>

      <div class="form-group mt-3">
        <label>UTR / Transaction ID <span style="color:var(--danger)">*</span></label>
        <input type="text" name="utr" id="utrField" class="form-control"
               placeholder="e.g. 123456789012" value="<?= s($_POST['utr'] ?? '') ?>">
        <div class="text-xs text-muted mt-1">12-digit number shown in your UPI app after payment</div>
      </div>

      <div class="form-group mt-2">
        <label>Payment Screenshot <span style="color:var(--danger)">*</span></label>
        <input type="file" name="receipt" id="receiptField" class="form-control" accept="image/*">
        <div class="text-xs text-muted mt-1">Upload a screenshot showing the successful transaction</div>
      </div>

    </div>

    <!-- ── Cash Panel ── -->
    <div id="panel-cash">
      <div class="alert alert-info" style="margin-bottom:1rem">
        💵 <strong>Cash on Delivery</strong> — You will pay <strong><?= formatINR($booking['total_price']) ?></strong> directly to the car owner at the time of pickup. The admin will confirm once the owner acknowledges receipt.
      </div>
      <div class="text-sm text-muted">No UTR or screenshot needed for cash payments.</div>
    </div>

    <div class="alert alert-info mt-3" style="font-size:.85rem">
      After submitting, the admin will verify and confirm your payment before the booking proceeds.
    </div>

    <button type="submit" class="btn btn-success mt-3 w-full" style="justify-content:center">
      Submit Payment
    </button>

  </form>
</div>

<script>
// Copy UPI ID
function copyUpi() {
    var txt = document.getElementById('upiIdText').textContent.trim();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(txt).then(function(){ alert('UPI ID copied!'); }).catch(function(){ prompt('Copy this UPI ID:', txt); });
    } else {
        prompt('Copy this UPI ID:', txt);
    }
}

// On submit: only require UTR+screenshot if UPI is selected
document.getElementById('payForm').addEventListener('submit', function(e) {
    var method = document.querySelector('input[name="method"]:checked').value;
    var utr    = document.getElementById('utrField');
    var shot   = document.getElementById('receiptField');
    if (method === 'upi_qr') {
        if (!utr.value.trim()) { alert('Please enter your UTR / Transaction ID.'); e.preventDefault(); return; }
        if (!shot.files || shot.files.length === 0) { alert('Please upload a payment screenshot.'); e.preventDefault(); return; }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>