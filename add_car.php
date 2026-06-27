<?php
require_once __DIR__ . '/../includes/helpers.php';
requireRole('/rentx/index.php', 'owner');
$pageTitle = 'Add Car';
$uid = $_SESSION['user_id'];
$db  = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $image = uploadImage($_FILES['image'], 'uploads/cars/');
    }
    $db->prepare("
        INSERT INTO cars (owner_id,brand,model,year,color,plate,category,seats,fuel,transmission,price_day,image,description,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')
    ")->execute([
        $uid,
        trim($_POST['brand']),  trim($_POST['model']),
        (int)$_POST['year'],   trim($_POST['color']          ?? ''),
        trim($_POST['plate']), trim($_POST['category']       ?? 'sedan'),
        (int)($_POST['seats'] ?? 5),
        trim($_POST['fuel']   ?? 'petrol'),
        trim($_POST['transmission'] ?? 'automatic'),
        (float)$_POST['price_day'],
        $image,
        trim($_POST['description'] ?? ''),
    ]);

    notifyAllAdmins('New Car Submitted', "Owner #{$uid} submitted a new car for approval.");
    addNotif($uid, 'Car Submitted', "Your car has been submitted and is awaiting admin approval.");

    setFlash('success', 'Car submitted for approval. Admin will review shortly.');
    header('Location: /rentx/owner/my_cars.php'); exit;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-container" style="max-width:760px">
  <a href="/rentx/owner/my_cars.php" class="text-muted text-sm" style="display:inline-block;margin:1rem 0">← My Cars</a>
  <div class="section-title mb-3">Submit New Car</div>
  <div class="alert alert-info mb-3">Your car will be reviewed by an admin before it goes live.</div>

  <form method="POST" enctype="multipart/form-data" class="card">
    <div class="form-grid">
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
        <label>Number of Seats</label>
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
        <label>Price per Day (INR) *</label>
        <input type="number" name="price_day" class="form-control" placeholder="99.00" step="0.01" required>
      </div>
      <div class="form-group">
        <label>Car Image</label>
        <input type="file" name="image" class="form-control" accept="image/*">
      </div>
    </div>
    <div class="form-group mt-2">
      <label>Description</label>
      <textarea name="description" class="form-control" rows="3" placeholder="Describe your car's features..."></textarea>
    </div>
    <div class="flex gap-2 mt-3">
      <button type="submit" class="btn btn-primary">Submit for Approval</button>
      <a href="/rentx/owner/my_cars.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
