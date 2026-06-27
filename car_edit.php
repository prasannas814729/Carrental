<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'admin', 'owner');
$pageTitle = 'Edit Car';

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT c.*, u.name AS owner_name FROM cars c JOIN users u ON u.id=c.owner_id WHERE c.id=?");
$stmt->execute([$id]);
$car  = $stmt->fetch();
if (!$car) { setFlash('error','Car not found.'); header('Location:/rentx/admin/cars.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image = $car['image'];
    if (!empty($_FILES['image']['name'])) {
        $uploaded = uploadImage($_FILES['image'], 'uploads/cars/');
        if ($uploaded) $image = $uploaded;
    }
    $db->prepare("
        UPDATE cars SET brand=?,model=?,year=?,color=?,plate=?,category=?,seats=?,fuel=?,transmission=?,price_day=?,image=?,description=?,status=?
        WHERE id=?
    ")->execute([
        trim($_POST['brand']), trim($_POST['model']),
        (int)$_POST['year'],  trim($_POST['color'] ?? ''),
        trim($_POST['plate']),trim($_POST['category'] ?? 'sedan'),
        (int)($_POST['seats'] ?? 5),
        trim($_POST['fuel'] ?? 'petrol'),
        trim($_POST['transmission'] ?? 'automatic'),
        (float)$_POST['price_day'],
        $image,
        trim($_POST['description'] ?? ''),
        trim($_POST['status']),
        $id,
    ]);
    setFlash('success','Car updated successfully.');
    header('Location:/rentx/admin/cars.php'); exit;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container" style="max-width:800px">
  <a href="/rentx/admin/cars.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← Back to Cars</a>
  <div class="section-title mb-3">Edit Car: <?= s($car['brand']) ?> <?= s($car['model']) ?></div>

  <form method="POST" enctype="multipart/form-data" class="card">
    <div class="form-grid">
      <div class="form-group">
        <label>Brand *</label>
        <input type="text" name="brand" class="form-control" value="<?= s($car['brand']) ?>" required>
      </div>
      <div class="form-group">
        <label>Model *</label>
        <input type="text" name="model" class="form-control" value="<?= s($car['model']) ?>" required>
      </div>
      <div class="form-group">
        <label>Year *</label>
        <input type="number" name="year" class="form-control" value="<?= s($car['year']) ?>" required>
      </div>
      <div class="form-group">
        <label>Color</label>
        <input type="text" name="color" class="form-control" value="<?= s($car['color']) ?>">
      </div>
      <div class="form-group">
        <label>Plate Number *</label>
        <input type="text" name="plate" class="form-control" value="<?= s($car['plate']) ?>" required>
      </div>
      <div class="form-group">
        <label>Category</label>
        <select name="category" class="form-control">
          <?php foreach (['sedan','suv','luxury','sports','van','truck'] as $cat): ?>
            <option value="<?= $cat ?>" <?= $car['category']===$cat?'selected':'' ?>><?= ucfirst($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Seats</label>
        <input type="number" name="seats" class="form-control" value="<?= $car['seats'] ?>">
      </div>
      <div class="form-group">
        <label>Fuel</label>
        <select name="fuel" class="form-control">
          <?php foreach (['petrol','diesel','electric','hybrid'] as $f): ?>
            <option value="<?= $f ?>" <?= $car['fuel']===$f?'selected':'' ?>><?= ucfirst($f) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Transmission</label>
        <select name="transmission" class="form-control">
          <option value="automatic" <?= $car['transmission']==='automatic'?'selected':'' ?>>Automatic</option>
          <option value="manual"    <?= $car['transmission']==='manual'?'selected':'' ?>>Manual</option>
        </select>
      </div>
      <div class="form-group">
        <label>Price per Day ($)</label>
        <input type="number" name="price_day" class="form-control" value="<?= $car['price_day'] ?>" step="0.01">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <?php foreach (['pending','available','rented','maintenance','rejected'] as $st): ?>
            <option value="<?= $st ?>" <?= $car['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Car Image</label>
        <?php if ($car['image']): ?>
          <img src="/rentx/<?= s($car['image']) ?>" style="width:100%;height:120px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
        <?php endif; ?>
        <input type="file" name="image" class="form-control" accept="image/*">
      </div>
    </div>
    <div class="form-group mt-2">
      <label>Description</label>
      <textarea name="description" class="form-control"><?= s($car['description']) ?></textarea>
    </div>
    <div class="flex gap-2 mt-3">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="/rentx/admin/cars.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
