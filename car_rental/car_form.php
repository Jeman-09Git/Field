<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$categories = $pdo->query("SELECT category_id, category_name, daily_rate FROM categories ORDER BY category_name")->fetchAll();

$car = ['make' => '', 'model' => '', 'year' => date('Y'), 'plate_number' => '', 'category_id' => '', 'status' => 'Available'];
if ($id) {
    $st = $pdo->prepare("SELECT * FROM cars WHERE car_id = ?");
    $st->execute([$id]);
    $car = $st->fetch() ?: $car;
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['make', 'model', 'year', 'plate_number', 'category_id', 'status'] as $f) {
        $car[$f] = trim($_POST[$f] ?? '');
    }
    if ($car['make'] === '' || $car['model'] === '' || $car['plate_number'] === '') $errors[] = 'Make, model and plate number are required.';
    if (!ctype_digit((string)$car['year']) || $car['year'] < 1980 || $car['year'] > date('Y') + 1) $errors[] = 'Enter a valid year.';
    if (!(int)$car['category_id']) $errors[] = 'Choose a category.';
    if (!in_array($car['status'], ['Available', 'Rented', 'Under Maintenance'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        try {
            if ($id) {
                $pdo->prepare("UPDATE cars SET category_id=?, make=?, model=?, year=?, plate_number=?, status=? WHERE car_id=?")
                    ->execute([$car['category_id'], $car['make'], $car['model'], $car['year'], $car['plate_number'], $car['status'], $id]);
                flash('success', 'Car updated.');
            } else {
                $pdo->prepare("INSERT INTO cars (category_id, make, model, year, plate_number, status) VALUES (?,?,?,?,?,?)")
                    ->execute([$car['category_id'], $car['make'], $car['model'], $car['year'], $car['plate_number'], $car['status']]);
                flash('success', 'Car added.');
            }
            redirect('cars.php');
        } catch (PDOException $ex) {
            $errors[] = 'Could not save. That plate number may already exist.';
        }
    }
}

$pageTitle = $id ? 'Edit car' : 'Add car';
$active    = 'cars';
$crumbs    = [['Home', 'index.php'], ['Cars', 'cars.php'], [$id ? 'Edit' : 'New', null]];
require __DIR__ . '/includes/header.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row"><div class="col-lg-7">
<form method="post" class="card card-primary">
  <div class="card-header"><h3 class="card-title">Car details</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group col-md-6"><label for="make">Make *</label><input class="form-control" id="make" name="make" value="<?= e($car['make']) ?>" required></div>
      <div class="form-group col-md-6"><label for="model">Model *</label><input class="form-control" id="model" name="model" value="<?= e($car['model']) ?>" required></div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-6"><label for="year">Year *</label><input type="number" class="form-control" id="year" name="year" value="<?= e($car['year']) ?>" required></div>
      <div class="form-group col-md-6"><label for="plate_number">Plate number *</label><input class="form-control" id="plate_number" name="plate_number" value="<?= e($car['plate_number']) ?>" placeholder="T123 ABC" required></div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-6">
        <label for="category_id">Category *</label>
        <select class="form-control" id="category_id" name="category_id" required>
          <option value="">Select...</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= (string)$car['category_id'] === (string)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?> (<?= money($c['daily_rate']) ?>/day)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-6">
        <label for="status">Status</label>
        <select class="form-control" id="status" name="status">
          <?php foreach (['Available', 'Rented', 'Under Maintenance'] as $s): ?>
            <option <?= $car['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save"></i> Save car</button> <a href="cars.php" class="btn btn-default">Cancel</a></div>
</form>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
