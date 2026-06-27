<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pageTitle = 'My Profile';

$db  = getDB();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!$name) { setFlash('error', 'Name is required.'); }
        else {
            // Handle document uploads
            $extraFields = '';
            $extraVals   = [];

            if (!empty($_FILES['driving_licence']['name'])) {
                $path = uploadImage($_FILES['driving_licence'], 'uploads/licences/');
                if ($path) { $extraFields .= ',driving_licence=?'; $extraVals[] = $path; }
            }
            if (!empty($_FILES['rc_book']['name'])) {
                $path = uploadImage($_FILES['rc_book'], 'uploads/documents/');
                if ($path) { $extraFields .= ',rc_book=?'; $extraVals[] = $path; }
            }

            $params = array_merge([$name, $phone], $extraVals, [$uid]);
            $db->prepare("UPDATE users SET name=?, phone=?{$extraFields} WHERE id=?")->execute($params);
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['phone'] = $phone;
            setFlash('success', 'Profile updated.');
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password FROM users WHERE id=?"); $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!password_verify($current, $user['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'Passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            setFlash('success', 'Password changed successfully.');
        }
    }
    header('Location: /rentx/profile.php'); exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$uid]);
$user = $stmt->fetch();

include __DIR__ . '/includes/header.php';
?>
<div class="page-container" style="max-width:680px">
  <div class="section-title mt-3 mb-3">My Profile</div>

  <div class="card mb-3">
    <div class="flex items-center gap-2 mb-3">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:#000;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700">
        <?= strtoupper(substr($user['name'],0,1)) ?>
      </div>
      <div>
        <div style="font-size:1.1rem;font-weight:600"><?= s($user['name']) ?></div>
        <span class="tag tag-gold"><?= strtoupper($user['role']) ?></span>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_profile">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" class="form-control" value="<?= s($user['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" class="form-control" value="<?= s($user['phone'] ?? '') ?>" placeholder="+1 234 567 8900">
        </div>
        <div class="form-group">
          <label>Email (read-only)</label>
          <input type="email" class="form-control" value="<?= s($user['email']) ?>" disabled>
        </div>
        <div class="form-group">
          <label>Role (read-only)</label>
          <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
        </div>
      </div>

      <!-- Documents section -->
      <?php if ($user['role'] === 'user'): ?>
      <hr class="divider mt-3">
      <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">Verification Document</div>
      <div class="form-group">
        <label>Driving Licence</label>
        <?php if (!empty($user['driving_licence'])): ?>
          <div style="margin-bottom:.6rem">
            <?php $ext = strtolower(pathinfo($user['driving_licence'], PATHINFO_EXTENSION)); ?>
            <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
              <img src="/rentx/<?= s($user['driving_licence']) ?>" style="max-width:260px;border-radius:8px;border:1px solid var(--border);display:block;margin-bottom:.4rem">
            <?php else: ?>
              <a href="/rentx/<?= s($user['driving_licence']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="margin-bottom:.4rem">📄 View Current Licence</a><br>
            <?php endif; ?>
            <span class="text-xs text-muted">Upload a new file to replace it.</span>
          </div>
        <?php else: ?>
          <div class="alert alert-error" style="padding:.5rem .8rem;font-size:.82rem;margin-bottom:.6rem">⚠ No driving licence on file. Required for bookings.</div>
        <?php endif; ?>
        <input type="file" name="driving_licence" class="form-control" accept="image/*,.pdf">
      </div>

      <?php elseif ($user['role'] === 'owner'): ?>
      <hr class="divider mt-3">
      <div class="text-xs text-muted mb-2" style="text-transform:uppercase;letter-spacing:.08em">Verification Documents</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Driving Licence</label>
          <?php if (!empty($user['driving_licence'])): ?>
            <div style="margin-bottom:.5rem">
              <?php $ext = strtolower(pathinfo($user['driving_licence'], PATHINFO_EXTENSION)); ?>
              <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                <img src="/rentx/<?= s($user['driving_licence']) ?>" style="max-width:200px;border-radius:8px;border:1px solid var(--border);display:block;margin-bottom:.3rem">
              <?php else: ?>
                <a href="/rentx/<?= s($user['driving_licence']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="margin-bottom:.4rem">📄 View Licence</a><br>
              <?php endif; ?>
              <span class="text-xs text-muted">Replace:</span>
            </div>
          <?php else: ?>
            <div class="alert alert-error" style="padding:.4rem .7rem;font-size:.8rem;margin-bottom:.5rem">⚠ Not uploaded</div>
          <?php endif; ?>
          <input type="file" name="driving_licence" class="form-control" accept="image/*,.pdf">
        </div>
        <div class="form-group">
          <label>RC Book (Vehicle Registration)</label>
          <?php if (!empty($user['rc_book'])): ?>
            <div style="margin-bottom:.5rem">
              <?php $ext = strtolower(pathinfo($user['rc_book'], PATHINFO_EXTENSION)); ?>
              <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                <img src="/rentx/<?= s($user['rc_book']) ?>" style="max-width:200px;border-radius:8px;border:1px solid var(--border);display:block;margin-bottom:.3rem">
              <?php else: ?>
                <a href="/rentx/<?= s($user['rc_book']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="margin-bottom:.4rem">📄 View RC Book</a><br>
              <?php endif; ?>
              <span class="text-xs text-muted">Replace:</span>
            </div>
          <?php else: ?>
            <div class="alert alert-error" style="padding:.4rem .7rem;font-size:.8rem;margin-bottom:.5rem">⚠ Not uploaded</div>
          <?php endif; ?>
          <input type="file" name="rc_book" class="form-control" accept="image/*,.pdf">
        </div>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
    </form>
  </div>

  <div class="card">
    <h3 class="mb-3">Change Password</h3>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group">
        <label>Current Password</label>
        <input type="password" name="current_password" class="form-control" required>
      </div>
      <div class="form-group mt-2">
        <label>New Password</label>
        <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required>
      </div>
      <div class="form-group mt-2">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-outline mt-3">Update Password</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
