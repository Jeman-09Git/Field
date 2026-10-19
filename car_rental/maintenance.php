<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $carId = (int)($_POST['car_id'] ?? 0);
        $date  = $_POST['maintenance_date'] ?? date('Y-m-d');
        $desc  = trim($_POST['description'] ?? '');
        $cost  = (float)($_POST['cost'] ?? 0);
        if (!$carId || $desc === '' || $cost < 0) {
            flash('danger', 'Choose a car, describe the work, and enter a cost of 0 or more.');
        } else {
            $st = $pdo->prepare("SELECT status FROM cars WHERE car_id=?");
            $st->execute([$carId]);
            $carStatus = $st->fetchColumn();
            if (!empty($_POST['block_car']) && $carStatus === 'Rented') {
                flash('danger', 'This car is currently rented out. Wait until it is returned before sending it to maintenance.');
            } else {
                $pdo->prepare("INSERT INTO maintenance (car_id, maintenance_date, description, cost) VALUES (?,?,?,?)")
                    ->execute([$carId, $date, $desc, $cost]);
                if (!empty($_POST['block_car'])) {
                    $pdo->prepare("UPDATE cars SET status='Under Maintenance' WHERE car_id=?")->execute([$carId]);
                }
                flash('success', 'Maintenance record saved.');
            }
        }
    } elseif ($action === 'available') {
        $pdo->prepare("UPDATE cars SET status='Available' WHERE car_id=? AND status='Under Maintenance'")->execute([(int)$_POST['car_id']]);
        flash('success', 'Car is available again.');
    }
    redirect('maintenance.php');
}

$pageTitle = 'Maintenance';
$active    = 'maintenance';
$crumbs    = [['Home', 'index.php'], ['Maintenance', null]];
require __DIR__ . '/includes/header.php';

$records = $pdo->query("SELECT m.*, ca.plate_number, ca.make, ca.model, ca.status AS car_status
                        FROM maintenance m JOIN cars ca ON ca.car_id = m.car_id
                        ORDER BY m.maintenance_date DESC, m.maintenance_id DESC")->fetchAll();
$cars = $pdo->query("SELECT car_id, plate_number, make, model, status FROM cars ORDER BY make, model")->fetchAll();
$totalCost = array_sum(array_column($records, 'cost'));
?>
<div class="row">
  <div class="col-lg-4">
    <form method="post" class="card card-primary">
      <div class="card-header"><h3 class="card-title">Log maintenance</h3></div>
      <div class="card-body">
        <input type="hidden" name="action" value="add">
        <div class="form-group"><label for="car_id">Car *</label>
          <select class="form-control" id="car_id" name="car_id" required>
            <option value="">Select car...</option>
            <?php foreach ($cars as $c): ?>
              <option value="<?= $c['car_id'] ?>"><?= e($c['plate_number']) ?> - <?= e($c['make'] . ' ' . $c['model']) ?> (<?= e($c['status']) ?>)</option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label for="maintenance_date">Date *</label>
          <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label for="description">Work done *</label>
          <input class="form-control" id="description" name="description" maxlength="255" placeholder="e.g. Oil change and brake inspection" required></div>
        <div class="form-group"><label for="cost">Cost *</label>
          <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
          <input type="number" min="0" step="1000" class="form-control" id="cost" name="cost" value="0" required></div></div>
        <div class="custom-control custom-switch">
          <input type="checkbox" class="custom-control-input" id="block_car" name="block_car" value="1">
          <label class="custom-control-label" for="block_car">Mark car as Under Maintenance</label>
        </div>
      </div>
      <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save"></i> Save record</button></div>
    </form>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h3 class="card-title"><?= count($records) ?> record(s) &middot; total cost <b><?= money($totalCost) ?></b></h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover">
          <thead><tr><th>ID</th><th>Car</th><th>Date</th><th>Work done</th><th class="text-right">Cost</th><th>Car status</th></tr></thead>
          <tbody>
          <?php foreach ($records as $m): ?>
            <tr>
              <td><?= $m['maintenance_id'] ?></td>
              <td><b><?= e($m['plate_number']) ?></b> <small class="text-muted"><?= e($m['make'] . ' ' . $m['model']) ?></small></td>
              <td class="text-nowrap"><?= e($m['maintenance_date']) ?></td>
              <td><?= e($m['description']) ?></td>
              <td class="text-right text-nowrap"><?= money($m['cost']) ?></td>
              <td class="text-nowrap"><?= carBadge($m['car_status']) ?>
                <?php if ($m['car_status'] === 'Under Maintenance'): ?>
                  <form method="post" class="d-inline"><input type="hidden" name="action" value="available"><input type="hidden" name="car_id" value="<?= $m['car_id'] ?>">
                    <button class="btn btn-xs btn-success" title="Work finished"><i class="fas fa-check"></i></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$records): ?><tr><td colspan="6" class="text-center text-muted p-4">No maintenance recorded yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
