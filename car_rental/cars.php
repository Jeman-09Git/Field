<?php
require_once __DIR__ . '/config.php';
$u = require_login();
$admin = $u['role'] === 'admin';
$today = date('Y-m-d');

/* ================= actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---- customer: rent a car ---- */
    if ($action === 'rent') {
        $carId  = (int)($_POST['car_id'] ?? 0);
        $from   = $_POST['rental_date'] ?? '';
        $to     = $_POST['return_date'] ?? '';
        $dep    = max(0, (float)($_POST['deposit'] ?? 0));
        $method = in_array($_POST['method'] ?? '', ['Cash', 'Card', 'Mobile Money'], true) ? $_POST['method'] : 'Cash';
        $err = null;

        if (!$u['customer_id'])                               $err = 'Staff accounts cannot rent cars. Sign in with a customer account.';
        elseif (!$carId)                                      $err = 'Choose a car.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $err = 'Enter valid dates.';
        elseif ($from < $today)                               $err = 'The rental date cannot be in the past.';
        elseif ($to <= $from)                                 $err = 'The return date must be after the rental date.';

        if (!$err) {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT ca.status, cat.daily_rate FROM cars ca JOIN categories cat ON cat.category_id = ca.category_id
                                 WHERE ca.car_id = ? FOR UPDATE");
            $st->execute([$carId]);
            $car = $st->fetch();
            if (!$car) {
                $err = 'That car does not exist.';
            } elseif ($car['status'] === 'Under Maintenance') {
                $err = 'This car is under maintenance and cannot be rented.';
            } else {
                $st = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE car_id = ? AND status = 'Ongoing' AND rental_date < ? AND return_date > ?");
                $st->execute([$carId, $to, $from]);
                if ($car['status'] === 'Rented' || $st->fetchColumn() > 0) $err = 'This car is already booked for these dates.';
            }
            if (!$err) {
                $total = rentalDays($from, $to) * (float)$car['daily_rate'];
                $dep = min($dep, $total);
                $pdo->prepare("INSERT INTO rentals (customer_id, car_id, rental_date, return_date, total_amount, status) VALUES (?,?,?,?,?, 'Ongoing')")
                    ->execute([$u['customer_id'], $carId, $from, $to, $total]);
                $rid = $pdo->lastInsertId();
                $pdo->prepare("UPDATE cars SET status='Rented' WHERE car_id=?")->execute([$carId]);
                if ($dep > 0) {
                    $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_date, payment_method, payment_status) VALUES (?,?,CURDATE(),?, 'Paid')")
                        ->execute([$rid, $dep, $method]);
                }
                $pdo->commit();
                flash('success', "Rental #$rid confirmed. Total: " . money($total) . ($dep > 0 ? ', deposit paid: ' . money($dep) : '') . '.');
                redirect('rentals.php');
            }
            $pdo->rollBack();
        }
        flash('danger', $err);
        redirect('cars.php');
    }

    /* ---- staff: add / edit / delete car ---- */
    if ($admin && $action === 'save_car') {
        $id = (int)($_POST['car_id'] ?? 0);
        $f = [];
        foreach (['make', 'model', 'year', 'plate_number', 'category_id', 'status'] as $k) $f[$k] = trim($_POST[$k] ?? '');
        if ($f['make'] === '' || $f['model'] === '' || $f['plate_number'] === '' || !(int)$f['category_id']
            || !ctype_digit($f['year']) || $f['year'] < 1980 || $f['year'] > date('Y') + 1
            || !in_array($f['status'], ['Available', 'Rented', 'Under Maintenance'], true)) {
            flash('danger', 'Fill in every field correctly (make, model, a valid year, plate number, category, status).');
        } else {
            try {
                if ($id) {
                    $pdo->prepare("UPDATE cars SET category_id=?, make=?, model=?, year=?, plate_number=?, status=? WHERE car_id=?")
                        ->execute([$f['category_id'], $f['make'], $f['model'], $f['year'], $f['plate_number'], $f['status'], $id]);
                    flash('success', 'Car updated.');
                } else {
                    $pdo->prepare("INSERT INTO cars (category_id, make, model, year, plate_number, status) VALUES (?,?,?,?,?,?)")
                        ->execute([$f['category_id'], $f['make'], $f['model'], $f['year'], $f['plate_number'], $f['status']]);
                    flash('success', 'Car added.');
                }
            } catch (PDOException $ex) {
                flash('danger', 'Could not save. That plate number may already exist.');
            }
        }
        redirect('cars.php');
    }
    if ($admin && $action === 'delete_car') {
        try {
            $pdo->prepare("DELETE FROM cars WHERE car_id = ?")->execute([(int)$_POST['id']]);
            flash('success', 'Car deleted.');
        } catch (PDOException $ex) {
            flash('danger', 'This car has rental or maintenance records, so it cannot be deleted. Change its status instead.');
        }
        redirect('cars.php');
    }
    redirect('cars.php');
}

/* ================= page ================= */
$q      = trim($_GET['q'] ?? '');
$cat    = (int)($_GET['category'] ?? 0);
$status = $admin ? ($_GET['status'] ?? '') : 'Available';

$sql = "SELECT ca.*, cat.category_name, cat.daily_rate FROM cars ca JOIN categories cat ON cat.category_id = ca.category_id WHERE 1=1";
$params = [];
if ($q !== '') { $sql .= " AND (ca.plate_number LIKE ? OR ca.make LIKE ? OR ca.model LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($cat) { $sql .= " AND ca.category_id = ?"; $params[] = $cat; }
if (in_array($status, ['Available', 'Rented', 'Under Maintenance'], true)) { $sql .= " AND ca.status = ?"; $params[] = $status; }
$sql .= " ORDER BY ca.make, ca.model";
$st = $pdo->prepare($sql);
$st->execute($params);
$cars = $st->fetchAll();
$categories = $pdo->query("SELECT category_id, category_name, daily_rate FROM categories ORDER BY category_name")->fetchAll();

layout_start($admin ? 'Cars' : 'Rent a car', 'cars', [['Home', 'index.php'], [$admin ? 'Cars' : 'Rent a car', null]]);
?>

<div class="card card-outline card-primary"><div class="card-body">
  <form method="get" class="form-row align-items-end">
    <div class="form-group col-md-4"><label for="q">Search</label>
      <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
        <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Make, model or plate"></div></div>
    <div class="form-group col-md-3"><label for="category">Category</label>
      <select class="form-control" id="category" name="category"><option value="0">All</option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['category_id'] ?>" <?= $cat === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
    <?php if ($admin): ?>
      <div class="form-group col-md-3"><label for="status">Status</label>
        <select class="form-control" id="status" name="status"><option value="">All</option>
          <?php foreach (['Available', 'Rented', 'Under Maintenance'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="form-group col-md-2"><button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <a href="cars.php" class="btn btn-default">Reset</a></div>
  </form>
</div></div>

<?php if ($admin): /* ---------- staff: manage cars ---------- */ ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><?= count($cars) ?> car(s)</h3>
      <div class="card-tools"><button class="btn btn-primary btn-sm js-new" data-target="#carModal"><i class="fas fa-plus"></i> Add car</button></div></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-hover">
        <thead><tr><th>ID</th><th>Plate</th><th>Car</th><th>Year</th><th>Category</th><th class="text-right">Daily rate</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($cars as $c): ?>
          <tr><td><?= $c['car_id'] ?></td><td><b><?= e($c['plate_number']) ?></b></td><td><?= e($c['make'] . ' ' . $c['model']) ?></td><td><?= e($c['year']) ?></td>
              <td><?= e($c['category_name']) ?></td><td class="text-right"><?= money($c['daily_rate']) ?></td><td><?= carBadge($c['status']) ?></td>
              <td><div class="btn-group btn-group-sm">
                <button class="btn btn-info js-edit" title="Edit" data-target="#carModal" data-row="<?= e(json_encode(array_intersect_key($c, array_flip(['car_id', 'make', 'model', 'year', 'plate_number', 'category_id', 'status'])))) ?>"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger js-confirm" title="Delete" data-action="delete_car" data-id="<?= $c['car_id'] ?>" data-title="Delete car" data-text="Delete <?= e($c['plate_number']) ?>? This cannot be undone." data-label="Delete"><i class="fas fa-trash"></i></button>
              </div></td></tr>
        <?php endforeach; ?>
        <?php if (!$cars): ?><tr><td colspan="8" class="text-center text-muted p-4">No cars match your search.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="carModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_car"><input type="hidden" name="car_id" value="0">
      <div class="modal-header"><h5 class="modal-title">Car details</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-6"><label>Make *</label><input class="form-control" name="make" required></div>
          <div class="form-group col-6"><label>Model *</label><input class="form-control" name="model" required></div>
        </div>
        <div class="form-row">
          <div class="form-group col-6"><label>Year *</label><input type="number" class="form-control" name="year" value="<?= date('Y') ?>" required></div>
          <div class="form-group col-6"><label>Plate number *</label><input class="form-control" name="plate_number" placeholder="T123 ABC" required></div>
        </div>
        <div class="form-row">
          <div class="form-group col-6"><label>Category *</label>
            <select class="form-control" name="category_id" required><option value="">Select...</option>
              <?php foreach ($categories as $c): ?><option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?> (<?= money($c['daily_rate']) ?>/day)</option><?php endforeach; ?></select></div>
          <div class="form-group col-6"><label>Status</label>
            <select class="form-control" name="status"><option>Available</option><option>Rented</option><option>Under Maintenance</option></select></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save"></i> Save car</button></div>
    </form></div>
  </div>

<?php else: /* ---------- customer: browse and rent ---------- */ ?>
  <?php if (!$cars): ?>
    <div class="callout callout-warning"><h5>No cars found</h5><p class="mb-0">No available car matches your search. Try another category or check back later.</p></div>
  <?php endif; ?>
  <div class="row">
    <?php foreach ($cars as $c): ?>
      <div class="col-md-6 col-xl-4">
        <div class="card card-outline card-primary">
          <div class="card-header"><h3 class="card-title"><i class="fas fa-car mr-1"></i> <?= e($c['make'] . ' ' . $c['model']) ?></h3><div class="card-tools"><?= carBadge($c['status']) ?></div></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-5">Plate</dt><dd class="col-7"><?= e($c['plate_number']) ?></dd>
              <dt class="col-5">Year</dt><dd class="col-7"><?= e($c['year']) ?></dd>
              <dt class="col-5">Category</dt><dd class="col-7"><?= e($c['category_name']) ?></dd>
              <dt class="col-5">Price</dt><dd class="col-7"><b><?= money($c['daily_rate']) ?></b> / day</dd>
            </dl>
          </div>
          <div class="card-footer">
            <button class="btn btn-primary btn-block js-edit" data-target="#rentModal"
              data-row="<?= e(json_encode(['car_id' => $c['car_id'], 'title' => $c['make'] . ' ' . $c['model'] . ' (' . $c['plate_number'] . ')', 'rate' => (float)$c['daily_rate'], 'rate_text' => money($c['daily_rate']) . ' per day'])) ?>">
              <i class="fas fa-key"></i> Rent this car</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="modal fade" id="rentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="rent"><input type="hidden" name="car_id" value="0">
      <div class="modal-header"><h5 class="modal-title">Rent <span data-fill="title"></span></h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <p class="text-muted" data-fill="rate_text"></p>
        <div class="form-row">
          <div class="form-group col-6"><label>Rental date *</label>
            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
              <input type="date" class="form-control" name="rental_date" value="<?= $today ?>" min="<?= $today ?>" required></div></div>
          <div class="form-group col-6"><label>Return date *</label>
            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-check"></i></span></div>
              <input type="date" class="form-control" name="return_date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div></div>
        </div>
        <div class="form-group"><label>Total amount <small class="text-muted">(days &times; daily rate)</small></label>
          <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div><input class="form-control" id="rentTotal" value="0" readonly></div></div>
        <div class="form-row">
          <div class="form-group col-6"><label>Deposit now (optional)</label>
            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div><input type="number" min="0" step="1000" class="form-control" name="deposit" value="0"></div></div>
          <div class="form-group col-6"><label>Payment method</label>
            <select class="form-control" name="method"><option>Cash</option><option>Card</option><option>Mobile Money</option></select></div>
        </div>
        <small class="text-muted">Payments are recorded in the system only; no real money is charged.</small>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-check"></i> Confirm rental</button></div>
    </form></div>
  </div>
<?php endif; ?>

<?php
layout_end(<<<'JS'
<script>
// live total in the rent modal
function calcTotal() {
  var d = $('#rentModal').data('rowData') || {}, rate = parseFloat(d.rate) || 0;
  var a = new Date($('#rentModal [name=rental_date]').val()), b = new Date($('#rentModal [name=return_date]').val());
  var days = Math.max(1, Math.round((b - a) / 86400000));
  $('#rentTotal').val(isNaN(days) ? '0' : (days * rate).toLocaleString('en-US'));
}
$('#rentModal').on('shown.bs.modal', calcTotal).on('input change', 'input', calcTotal);
</script>
JS);
