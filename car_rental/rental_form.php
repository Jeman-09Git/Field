<?php
require_once __DIR__ . '/config.php';

$customers = $pdo->query("SELECT customer_id, full_name, license_number FROM customers ORDER BY full_name")->fetchAll();
$cars = $pdo->query("SELECT ca.car_id, ca.plate_number, ca.make, ca.model, cat.category_name, cat.daily_rate
                     FROM cars ca JOIN categories cat ON cat.category_id = ca.category_id
                     WHERE ca.status = 'Available' ORDER BY ca.make, ca.model")->fetchAll();

$data = [
    'customer_id' => $_POST['customer_id'] ?? ($_GET['customer_id'] ?? ''),
    'car_id'      => $_POST['car_id'] ?? ($_GET['car_id'] ?? ''),
    'rental_date' => $_POST['rental_date'] ?? date('Y-m-d'),
    'return_date' => $_POST['return_date'] ?? date('Y-m-d', strtotime('+1 day')),
    'deposit'     => $_POST['deposit'] ?? '0',
    'method'      => $_POST['payment_method'] ?? 'Cash',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $customerId = (int)$data['customer_id'];
    $carId      = (int)$data['car_id'];
    $deposit    = (float)$data['deposit'];

    if (!$customerId) $errors[] = 'Please choose a customer.';
    if (!$carId)      $errors[] = 'Please choose a car.';
    if ($data['return_date'] <= $data['rental_date']) $errors[] = 'The return date must be after the rental date.';

    if (!$errors) {
        $st = $pdo->prepare("SELECT ca.status, cat.daily_rate FROM cars ca
                             JOIN categories cat ON cat.category_id = ca.category_id WHERE ca.car_id = ?");
        $st->execute([$carId]);
        $car = $st->fetch();

        if (!$car) {
            $errors[] = 'The selected car does not exist.';
        } elseif ($car['status'] === 'Under Maintenance') {
            $errors[] = 'This car is under maintenance and cannot be rented.';
        } else {
            // double-booking check: another ongoing rental overlapping these dates
            $st = $pdo->prepare("SELECT COUNT(*) FROM rentals
                                 WHERE car_id = ? AND status = 'Ongoing'
                                   AND rental_date < ? AND return_date > ?");
            $st->execute([$carId, $data['return_date'], $data['rental_date']]);
            if ($st->fetchColumn() > 0 || $car['status'] === 'Rented') {
                $errors[] = 'This car is already booked for these dates.';
            }
        }
    }

    if (!$errors) {
        $total = rentalDays($data['rental_date'], $data['return_date']) * (float)$car['daily_rate'];
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO rentals (customer_id, car_id, rental_date, return_date, total_amount, status)
                       VALUES (?,?,?,?,?, 'Ongoing')")
            ->execute([$customerId, $carId, $data['rental_date'], $data['return_date'], $total]);
        $rentalId = $pdo->lastInsertId();
        $pdo->prepare("UPDATE cars SET status='Rented' WHERE car_id=?")->execute([$carId]);
        if ($deposit > 0) {
            $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_date, payment_method, payment_status)
                           VALUES (?,?,CURDATE(),?, 'Paid')")
                ->execute([$rentalId, $deposit, $data['method']]);
        }
        $pdo->commit();
        flash('success', "Rental #$rentalId saved. Total amount: " . money($total) . '.');
        redirect('rentals.php');
    }
    foreach ($errors as $err) { $formErrors[] = $err; }
}

$pageTitle = 'New rental';
$active    = 'rentals';
$crumbs    = [['Home', 'index.php'], ['Rentals', 'rentals.php'], ['New', null]];
require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($formErrors)): ?>
  <div class="alert alert-danger">
    <h5><i class="icon fas fa-ban"></i> Rental not saved</h5>
    <ul class="mb-0"><?php foreach ($formErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">
    <form method="post" class="card card-primary">
      <div class="card-header"><h3 class="card-title">Rental details</h3></div>
      <div class="card-body">
        <div class="form-group">
          <label for="customer_id">Customer *</label>
          <select class="form-control" id="customer_id" name="customer_id" required>
            <option value="">Select customer...</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['customer_id'] ?>" <?= (string)$data['customer_id'] === (string)$c['customer_id'] ? 'selected' : '' ?>>
                <?= e($c['full_name']) ?> (<?= e($c['license_number']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="car_id">Car * <small class="text-muted">(available cars only)</small></label>
          <select class="form-control" id="car_id" name="car_id" required>
            <option value="">Select car...</option>
            <?php foreach ($cars as $c): ?>
              <option value="<?= $c['car_id'] ?>" data-rate="<?= $c['daily_rate'] ?>" <?= (string)$data['car_id'] === (string)$c['car_id'] ? 'selected' : '' ?>>
                <?= e($c['plate_number']) ?> - <?= e($c['make'] . ' ' . $c['model']) ?> (<?= e($c['category_name']) ?>, <?= money($c['daily_rate']) ?>/day)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="rental_date">Rental date *</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
              <input type="date" class="form-control" id="rental_date" name="rental_date" value="<?= e($data['rental_date']) ?>" required>
            </div>
          </div>
          <div class="form-group col-md-6">
            <label for="return_date">Return date *</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-check"></i></span></div>
              <input type="date" class="form-control" id="return_date" name="return_date" value="<?= e($data['return_date']) ?>" required>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label for="total">Total amount <small class="text-muted">(days &times; category daily rate, calculated)</small></label>
          <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
            <input type="text" class="form-control" id="total" value="0" readonly>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="deposit">Deposit paid now (optional)</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
              <input type="number" min="0" step="1000" class="form-control" id="deposit" name="deposit" value="<?= e($data['deposit']) ?>">
            </div>
          </div>
          <div class="form-group col-md-6">
            <label for="payment_method">Payment method</label>
            <select class="form-control" id="payment_method" name="payment_method">
              <?php foreach (['Cash', 'Card', 'Mobile Money'] as $m): ?>
                <option <?= $data['method'] === $m ? 'selected' : '' ?>><?= $m ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save rental</button>
        <a href="rentals.php" class="btn btn-default">Cancel</a>
      </div>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="callout callout-info">
      <h5>Booking rules</h5>
      <p class="mb-1">Only cars with status <b>Available</b> can be chosen.</p>
      <p class="mb-1">A car cannot be double-booked for overlapping dates.</p>
      <p class="mb-0">The return date must be after the rental date.</p>
    </div>
  </div>
</div>

<?php
$pageScript = <<<'JS'
<script>
function calcTotal() {
  var rate = parseFloat($('#car_id option:selected').data('rate')) || 0;
  var a = new Date($('#rental_date').val()), b = new Date($('#return_date').val());
  var days = Math.max(1, Math.round((b - a) / 86400000));
  var total = (isNaN(days) || !rate) ? 0 : days * rate;
  $('#total').val(total.toLocaleString('en-US'));
}
$('#car_id, #rental_date, #return_date').on('change input', calcTotal);
calcTotal();
</script>
JS;
require __DIR__ . '/includes/footer.php';
