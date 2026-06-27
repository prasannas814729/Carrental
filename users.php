<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin', 'owner');
$pageTitle = 'Users';

$db = getDB();

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)$_POST['user_id'];
    if ($action === 'suspend') {
        $db->prepare("UPDATE users SET status='suspended' WHERE id=? AND role='user'")->execute([$uid]);
        setFlash('success','User suspended.');
    } elseif ($action === 'activate') {
        $db->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$uid]);
        setFlash('success','User activated.');
    } elseif ($action === 'add_user') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'user';
        $pass  = password_hash($_POST['password'] ?? 'password123', PASSWORD_BCRYPT);
        $phone = trim($_POST['phone'] ?? '');
        $chk   = $db->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]);
        if ($chk->fetch()) { setFlash('error','Email already exists.'); }
        else {
            $db->prepare("INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,?,?)")->execute([$name,$email,$pass,$role,$phone]);
            setFlash('success','User created.');
        }
    }
    header('Location: /rentx/admin/users.php'); exit;
}

$roleFilter = $_GET['role'] ?? '';
$where = []; $params = [];
if ($roleFilter) { $where[] = "role = ?"; $params[] = $roleFilter; }

$sql = "SELECT u.*, (SELECT COUNT(*) FROM bookings WHERE user_id=u.id) AS booking_count FROM users u";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">Users Management</div>
      <div class="section-sub"><?= count($users) ?> user(s)</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('modal-add-user')">+ Add User</button>
  </div>

  <form method="GET" class="filters-bar auto-submit mb-2">
    <div class="form-group">
      <label>Filter by Role</label>
      <select name="role" class="form-control">
        <option value="">All Roles</option>
        <?php foreach (['user','owner','admin'] as $r): ?>
          <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Bookings</th><th>Status</th><th>Joined</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>#<?= $u['id'] ?></td>
          <td><strong><?= s($u['name']) ?></strong></td>
          <td><?= s($u['email']) ?></td>
          <td><?= s($u['phone'] ?: '—') ?></td>
          <td>
            <span class="tag <?= $u['role']==='admin'?'tag-gold':'' ?>"><?= ucfirst($u['role']) ?></span>
          </td>
          <td><?= $u['booking_count'] ?></td>
          <td>
            <span class="status <?= $u['status']==='active'?'status-active':'status-cancelled' ?>">
              <?= ucfirst($u['status']) ?>
            </span>
          </td>
          <td class="text-xs text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['role'] === 'user'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <?php if ($u['status'] === 'active'): ?>
                  <input type="hidden" name="action" value="suspend">
                  <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Suspend user?')">Suspend</button>
                <?php else: ?>
                  <input type="hidden" name="action" value="activate">
                  <button type="submit" class="btn btn-success btn-sm">Activate</button>
                <?php endif; ?>
              </form>
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

<!-- Add User Modal -->
<div class="modal-overlay" id="modal-add-user">
  <div class="modal">
    <div class="modal-header">
      <h3>Add New User</h3>
      <button class="modal-close" onclick="closeModal('modal-add-user')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="form-group mt-2">
        <label>Email *</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="form-group mt-2">
        <label>Phone</label>
        <input type="tel" name="phone" class="form-control">
      </div>
      <div class="form-group mt-2">
        <label>Role</label>
        <select name="role" class="form-control">
          <option value="user">User</option>
          <option value="owner">Owner</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="form-group mt-2">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Default: password123">
      </div>
      <div class="flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">Create User</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-user')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
