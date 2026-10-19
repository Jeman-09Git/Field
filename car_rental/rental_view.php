<?php
$pageTitle = 'Rental details';
$active    = 'rentals';
$crumbs    = [['Home', 'index.php'], ['Rentals', 'rentals.php'], ['Details', null]];
require __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT r.*, c.full_name, c.phone, c.license_number,
                            ca.plate_number, ca.make, ca.model, cat.category_name, cat.daily_rate
                     FROM rentals r
                     JOIN customers c ON c.customer_id = r.customer_id
                     JOIN cars ca ON ca.car_id = r.car_id
                     JOIN categories cat ON cat.category_id = ca.category_id
                     WHERE r.rental_id = ?");
$st->execute([$id]);
$r = $st->fetch();

if (!$r) {
    echo '<div class="alert alert-warning">Rental not found. <a href="rentals.php">Back to rentals</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$st = $pdo->prepare("SELECT * FROM payments WHERE rental_id = ? ORDER BY payment_id");
$st->execute([$id]);
$payments = $st->fetchAll();
$paid = 0;
foreach ($payments as $p) {
    if ($p['payment_status'] === 'Paid') $paid += $p['amount'];
    if ($p['payment_status'] === 'Refunded') $paid -= $p['amount'];
}
$balance = $r['total_amount'] - $paid;
?>
<div class="row">
  <div class="col-md-6">
    <div class="card card-primary card-outline">
      <div class="card-header"><h3 class="card-title">Rental #<?= $r['rental_id'] ?></h3><div class="card-tools"><?= rentalBadge($r) ?></div></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Customer</dt><dd class="col-sm-7"><?= e($r['full_name']) ?> (<?= e($r['phone']) ?>)</dd>
          <dt class="col-sm-5">Licence number</dt><dd class="col-sm-7"><?= e($r['license_number']) ?></dd>
          <dt class="col-sm-5">Car</dt><dd class="col-sm-7"><?= e($r['plate_number']) ?> - <?= e($r['make'] . ' ' . $r['model']) ?></dd>
          <dt class="col-sm-5">Category</dt><dd class="col-sm-7"><?= e($r['category_name']) ?> (<?= money($r['daily_rate']) ?>/day)</dd>
          <dt class="col-sm-5">Rental date</dt><dd class="col-sm-7"><?= e($r['rental_date']) ?></dd>
          <dt class="col-sm-5">Expected return</dt><dd class="col-sm-7"><?= e($r['return_date']) ?></dd>
          <dt class="col-sm-5">Actual return</dt><dd class="col-sm-7"><?= e($r['actual_return_date'] ?: '-') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="info-box">
      <span class="info-box-icon bg-info"><i class="fas fa-file-invoice-dollar"></i></span>
      <div class="info-box-content"><span class="info-box-text">Total amount</span><span class="info-box-number"><?= money($r['total_amount']) ?></span></div>
    </div>
    <div class="info-box">
      <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
      <div class="info-box-content"><span class="info-box-text">Paid so far</span><span class="info-box-number"><?= money($paid) ?></span></div>
    </div>
    <div class="info-box">
      <span class="info-box-icon <?= $balance > 0 ? 'bg-danger' : 'bg-success' ?>"><i class="fas fa-balance-scale"></i></span>
      <div class="info-box-content"><span class="info-box-text">Balance due</span><span class="info-box-number"><?= money(max(0, $balance)) ?></span></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title">Payments for this rental</h3>
    <div class="card-tools"><a href="payments.php?rental_id=<?= $r['rental_id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add payment</a></div></div>
  <div class="card-body table-responsive p-0">
    <table class="table">
      <thead><tr><th>ID</th><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr><td><?= $p['payment_id'] ?></td><td><?= e($p['payment_date']) ?></td><td><?= e($p['payment_method']) ?></td>
            <td class="text-right"><?= money($p['amount']) ?></td><td><?= paymentBadge($p['payment_status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="5" class="text-center text-muted">No payments yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<a href="rentals.php" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to rentals</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
