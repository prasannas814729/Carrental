<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin', 'owner');
$pageTitle = 'Manage Cars';

$db   = getDB();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_car') {
    $ownerId = (int)$_POST['owner_id'];
    $image   = null;
    if (!empty($_FILES['image']['name'])) {
        $image = uploadImage($_FILES['image'], 'uploads/cars/');
    }
    $db->prepare("
        INSERT INTO cars (owner_id,brand,model,year,color,plate,category,seats,fuel,transmission,price_day,image,description,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'available')
    ")->execute([
        $ownerId,
        trim($_POST['brand']),    trim($_POST['model']),
        (int)$_POST['year'],     trim($_POST['color']          ?? ''),
        trim($_POST['plate']),   trim($_POST['category']       ?? 'sedan'),
        (int)($_POST['seats']    ?? 5),
        trim($_POST['fuel']      ?? 'petrol'),
        trim($_POST['transmission'] ?? 'automatic'),
        (float)$_POST['price_day'],
        $image,
        trim($_POST['description'] ?? ''),
    ]);
    setFlash('success', 'Car added successfully.');
    header('Location: /rentx/admin/cars.php'); exit;
}

$statusFilter = $_GET['status'] ?? '';
$where  = [];
$params = [];
if ($statusFilter) { $where[] = "c.status = ?"; $params[] = $statusFilter; }

$sql  = "SELECT c.*, u.name AS owner_name FROM cars c JOIN users u ON u.id = c.owner_id";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($sql); $stmt->execute($params);
$cars  = $stmt->fetchAll();

$owners = $db->query("SELECT id, name FROM users WHERE role = 'owner' ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container-lg">
  <div class="section-header mt-3">
    <div>
      <div class="section-title">Cars Management</div>
      <div class="section-sub"><?= count($cars) ?> car(s) found</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('modal-add-car')">+ Add Car</button>
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

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Car</th><th>Owner</th><th>Category</th><th>Plate</th><th>Price/Day</th><th>Status</th><th>Added</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($cars as $c): ?>
        <tr>
          <td><strong><?= s($c['brand']) ?> <?= s($c['model']) ?></strong><br><span class="text-xs text-muted"><?= s($c['year']) ?> · <?= s($c['color']) ?></span></td>
          <td><?= s($c['owner_name']) ?></td>
          <td><?= ucfirst(s($c['category'])) ?></td>
          <td><?= s($c['plate']) ?></td>
          <td class="text-gold"><?= formatINR($c['price_day']) ?></td>
          <td><span class="status status-<?= s($c['status']) ?>"><?= ucfirst(s($c['status'])) ?></span></td>
          <td class="text-xs text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
          <td style="display:flex;gap:.3rem;flex-wrap:wrap">
            <a href="/rentx/admin/car_edit.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
            <?php if ($c['status'] === 'pending'): ?>
              <a href="/rentx/admin/car_approve.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">Review</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Car Modal -->
<div class="modal-overlay" id="modal-add-car">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h3>Add New Car</h3>
      <button class="modal-close" onclick="closeModal('modal-add-car')">×</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_car">
      <div class="form-grid">
        <div class="form-group">
          <label>Owner *</label>
          <select name="owner_id" class="form-control" required>
            <option value="">Select Owner</option>
            <?php foreach ($owners as $o): ?>
              <option value="<?= $o['id'] ?>"><?= s($o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Brand *</label>
          <input type="text" name="brand" class="form-control" placeholder="Toyota" required>
        </div>
        <div class="form-group">
          <label>Model *</label>
          <input type="text" name="model" class="form-control" placeholder="Camry" required>
        </div>
        <div class="form-group">
          <label>Year *</label>
          <input type="number" name="year" class="form-control" placeholder="2023" min="2000" max="2030" required>
        </div>
        <div class="form-group">
          <label>Color</label>
          <input type="text" name="color" class="form-control" placeholder="White">
        </div>
        <div class="form-group">
          <label>Plate Number *</label>
          <input type="text" name="plate" class="form-control" placeholder="ABC-1234" required>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category" class="form-control">
            <?php foreach (['sedan','suv','luxury','sports','van','truck'] as $cat): ?>
              <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Seats</label>
          <input type="number" name="seats" class="form-control" value="5" min="1" max="20">
        </div>
        <div class="form-group">
          <label>Fuel Type</label>
          <select name="fuel" class="form-control">
            <?php foreach (['petrol','diesel','electric','hybrid'] as $f): ?>
              <option value="<?= $f ?>"><?= ucfirst($f) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Transmission</label>
          <select name="transmission" class="form-control">
            <option value="automatic">Automatic</option>
            <option value="manual">Manual</option>
          </select>
        </div>
        <div class="form-group">
          <label>Price per Day ($) *</label>
          <input type="number" name="price_day" class="form-control" placeholder="99.00" step="0.01" required>
        </div>
        <div class="form-group">
          <label>Car Image</label>
          <input type="file" name="image" class="form-control" accept="image/*">
        </div>
      </div>
      <div class="form-group mt-2">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the car..."></textarea>
      </div>
      <div class="flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">Add Car</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-car')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
