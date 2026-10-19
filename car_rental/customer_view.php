<?php
$pageTitle = 'Customer details';
$active    = 'customers';
$crumbs    = [['Home', 'index.php'], ['Customers', 'customers.php'], ['Details', null]];
require __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
$st->execute([$id]);
$c = $st->fetch();
if (!$c) {
    echo '<div class="alert alert-warning">Customer not found. <a href="customers.php">Back to customers</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$st = $pdo->prepare("SELECT r.*, ca.plate_number, ca.make, ca.model FROM rentals r
                     JOIN cars ca ON ca.car_id = r.car_id WHERE r.customer_id = ? ORDER BY r.rental_id DESC");
$st->execute([$id]);
$rentals = $st->fetchAll();

$st = $pdo->prepare("SELECT p.* FROM payments p JOIN rentals r ON r.rental_id = p.rental_id
                     WHERE r.customer_id = ? ORDER BY p.payment_id DESC");
$st->execute([$id]);
$payments = $st->fetchAll();
?>
<div class="row">
  <div class="col-md-4">
    <div class="card card-primary card-outline">
      <div class="card-body box-profile">
        <h3 class="profile-username text-center"><?= e($c['full_name']) ?></h3>
        <p class="text-muted text-center">Customer #<?= $c['customer_id'] ?></p>
        <ul class="list-group list-group-unbordered mb-3">
          <li class="list-group-item"><b>Rentals</b> <span class="float-right"><?= count($rentals) ?></span></li>
          <li class="list-group-item"><b>Phone</b> <span class="float-right"><?= e($c['phone']) ?></span></li>
          <li class="list-group-item"><b>Email</b> <span class="float-right"><?= e($c['email'] ?: '-') ?></span></li>
          <li class="list-group-item"><b>Licence</b> <span class="float-right"><?= e($c['license_number']) ?></span></li>
          <li class="list-group-item"><b>Address</b> <span class="float-right"><?= e($c['address'] ?: '-') ?></span></li>
        </ul>
        <a href="rental_form.php?customer_id=<?= $c['customer_id'] ?>" class="btn btn-primary btn-block"><i class="fas fa-key"></i> New rental</a>
        <a href="customer_form.php?id=<?= $c['customer_id'] ?>" class="btn btn-default btn-block"><i class="fas fa-edit"></i> Edit profile</a>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <!-- Tabs -->
    <div class="card">
      <div class="card-header p-2">
        <ul class="nav nav-pills">
          <li class="nav-item"><a class="nav-link active" href="#history" data-toggle="tab">Rental history</a></li>
          <li class="nav-item"><a class="nav-link" href="#pay" data-toggle="tab">Payments</a></li>
        </ul>
      </div>
      <div class="card-body p-0">
        <div class="tab-content">
          <div class="tab-pane active table-responsive" id="history">
            <table class="table text-nowrap">
              <thead><tr><th>ID</th><th>Car</th><th>Rental date</th><th>Return date</th><th class="text-right">Total</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($rentals as $r): ?>
                <tr><td><a href="rental_view.php?id=<?= $r['rental_id'] ?>"><?= $r['rental_id'] ?></a></td>
                    <td><?= e($r['plate_number']) ?> <small class="text-muted"><?= e($r['make'] . ' ' . $r['model']) ?></small></td>
                    <td><?= e($r['rental_date']) ?></td><td><?= e($r['return_date']) ?></td>
                    <td class="text-right"><?= money($r['total_amount']) ?></td><td><?= rentalBadge($r) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$rentals): ?><tr><td colspan="6" class="text-center text-muted p-4">This customer has no rentals yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="tab-pane table-responsive" id="pay">
            <table class="table text-nowrap">
              <thead><tr><th>ID</th><th>Rental</th><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($payments as $p): ?>
                <tr><td><?= $p['payment_id'] ?></td><td>#<?= $p['rental_id'] ?></td><td><?= e($p['payment_date']) ?></td>
                    <td><?= e($p['payment_method']) ?></td><td class="text-right"><?= money($p['amount']) ?></td><td><?= paymentBadge($p['payment_status']) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-muted p-4">No payments yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
