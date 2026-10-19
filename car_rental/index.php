<?php
require_once __DIR__ . '/config.php';
$u = require_login();
$admin = $u['role'] === 'admin';
$today = date('Y-m-d');

layout_start($admin ? 'Dashboard' : 'My dashboard', 'dashboard', [['Home', 'index.php'], ['Dashboard', null]]);

if ($admin) {
    /* ============ STAFF DASHBOARD ============ */
    $totalCars     = (int)$pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
    $availableCars = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE status='Available'")->fetchColumn();
    $rentedCars    = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE status='Rented'")->fetchColumn();
    $activeRentals = (int)$pdo->query("SELECT COUNT(*) FROM rentals WHERE status='Ongoing'")->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM rentals WHERE status='Ongoing' AND return_date < ?");
    $st->execute([$today]);
    $overdueCount = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='Paid' AND DATE_FORMAT(payment_date,'%Y-%m') = ?");
    $st->execute([date('Y-m')]);
    $revenue = $st->fetchColumn();
    $utilization = $totalCars ? round($rentedCars / $totalCars * 100) : 0;
    $inMaintenance = $pdo->query("SELECT plate_number, make, model FROM cars WHERE status='Under Maintenance'")->fetchAll();
    $st = $pdo->prepare("SELECT r.rental_id, r.return_date, c.full_name, ca.plate_number FROM rentals r
                         JOIN customers c ON c.customer_id = r.customer_id JOIN cars ca ON ca.car_id = r.car_id
                         WHERE r.status='Ongoing' AND r.return_date < ? ORDER BY r.return_date");
    $st->execute([$today]);
    $overdue = $st->fetchAll();
    $recent = $pdo->query("SELECT r.*, c.full_name, ca.plate_number, ca.make, ca.model FROM rentals r
                           JOIN customers c ON c.customer_id = r.customer_id JOIN cars ca ON ca.car_id = r.car_id
                           ORDER BY r.rental_id DESC LIMIT 6")->fetchAll();
    $recentPay = $pdo->query("SELECT p.*, c.full_name FROM payments p JOIN rentals r ON r.rental_id = p.rental_id
                              JOIN customers c ON c.customer_id = r.customer_id ORDER BY p.payment_id DESC LIMIT 5")->fetchAll();
    $st = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $st->execute([$u['user_id']]);
    if (password_verify(ADMIN_PASSWORD, (string)$st->fetchColumn())): ?>
      <div class="callout callout-danger"><h5>Change the default staff password</h5>
        <p class="mb-0">This staff account still uses the password from the setup. Click <b>Password</b> at the top right and choose your own.</p></div>
    <?php endif; ?>
    <div class="row">
      <div class="col-lg-3 col-6"><div class="small-box bg-success">
        <div class="inner"><h3><?= $availableCars ?><sup style="font-size:20px"> / <?= $totalCars ?></sup></h3><p>Available cars</p></div>
        <div class="icon"><i class="fas fa-car"></i></div>
        <a href="cars.php?status=Available" class="small-box-footer">View cars <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-info">
        <div class="inner"><h3><?= $activeRentals ?></h3><p>Ongoing rentals</p></div>
        <div class="icon"><i class="fas fa-key"></i></div>
        <a href="rentals.php?status=Ongoing" class="small-box-footer">View rentals <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-danger">
        <div class="inner"><h3><?= $overdueCount ?></h3><p>Overdue returns</p></div>
        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        <a href="rentals.php?status=Overdue" class="small-box-footer">Review <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-warning">
        <div class="inner"><h3 style="font-size:1.6rem"><?= money($revenue) ?></h3><p>Paid this month</p></div>
        <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
        <a href="admin.php?tab=payments" class="small-box-footer">View payments <i class="fas fa-arrow-circle-right"></i></a></div></div>
    </div>
    <div class="row">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Recent rentals</h3></div>
          <div class="card-body table-responsive p-0">
            <table class="table table-hover">
              <thead><tr><th>ID</th><th>Customer</th><th>Car</th><th>Due back</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($recent as $r): ?>
                <tr><td><?= $r['rental_id'] ?></td><td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['plate_number']) ?> <small class="text-muted"><?= e($r['make'] . ' ' . $r['model']) ?></small></td>
                    <td><?= e($r['return_date']) ?></td><td><?= rentalBadge($r) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted">No rentals yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card"><div class="card-header"><h3 class="card-title">Fleet utilization</h3></div>
          <div class="card-body"><p class="mb-1"><?= $rentedCars ?> of <?= $totalCars ?> cars are rented out</p>
            <div class="progress"><div class="progress-bar bg-primary" style="width: <?= $utilization ?>%"><?= $utilization ?>%</div></div></div></div>
        <?php if ($overdue): ?>
          <div class="callout callout-danger"><h5>Overdue returns</h5>
            <?php foreach ($overdue as $o): ?><p class="mb-1"><b><?= e($o['plate_number']) ?></b> &ndash; <?= e($o['full_name']) ?> (due <?= e($o['return_date']) ?>)</p><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="callout callout-warning"><h5>Under maintenance</h5>
          <?php foreach ($inMaintenance as $m): ?><p class="mb-1"><b><?= e($m['plate_number']) ?></b> &ndash; <?= e($m['make'] . ' ' . $m['model']) ?></p><?php endforeach; ?>
          <?php if (!$inMaintenance): ?><p class="mb-0 text-muted">No cars in the workshop.</p><?php endif; ?></div>
      </div>
    </div>
    <div class="card"><div class="card-header"><h3 class="card-title">Recent payments</h3></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($recentPay as $p): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="far fa-credit-card mr-2"></i><?= e($p['full_name']) ?> &middot; <?= e($p['payment_method']) ?> &middot; <?= e($p['payment_date']) ?></span>
            <span><?= money($p['amount']) ?> <?= paymentBadge($p['payment_status']) ?></span></li>
        <?php endforeach; ?>
        <?php if (!$recentPay): ?><li class="list-group-item text-muted">No payments recorded yet.</li><?php endif; ?>
      </ul></div>
    <?php
} else {
    /* ============ CUSTOMER DASHBOARD ============ */
    $cid = $u['customer_id'];
    $st = $pdo->prepare("SELECT r.*, ca.plate_number, ca.make, ca.model, " . PAID_SQL . " AS paid FROM rentals r
                         JOIN cars ca ON ca.car_id = r.car_id WHERE r.customer_id = ? ORDER BY r.rental_id DESC");
    $st->execute([$cid]);
    $mine = $st->fetchAll();
    $ongoing = $totalPaid = $balance = $overdueMine = 0;
    foreach ($mine as $r) {
        $totalPaid += $r['paid'];
        if ($r['status'] === 'Ongoing') {
            $ongoing++;
            if ($r['return_date'] < $today) $overdueMine++;
        }
        if ($r['status'] !== 'Cancelled') $balance += max(0, $r['total_amount'] - $r['paid']);
    }
    $availableCars = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE status='Available'")->fetchColumn();
    ?>
    <div class="callout callout-info">
      <h5>Hello, <?= e($u['full_name']) ?></h5>
      <p class="mb-2"><?= $availableCars ?> car(s) are available to rent right now.</p>
      <a href="cars.php" class="btn btn-primary btn-sm"><i class="fas fa-car"></i> Rent a car</a>
    </div>
    <?php if ($overdueMine): ?>
      <div class="callout callout-danger"><h5>Return overdue</h5><p class="mb-0">You have <?= $overdueMine ?> rental(s) past the return date. Please return the car to the office.</p></div>
    <?php endif; ?>
    <div class="row">
      <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3><?= $ongoing ?></h3><p>Ongoing rentals</p></div><div class="icon"><i class="fas fa-key"></i></div>
        <a href="rentals.php?status=Ongoing" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-secondary"><div class="inner"><h3><?= count($mine) ?></h3><p>Total rentals</p></div><div class="icon"><i class="fas fa-history"></i></div>
        <a href="rentals.php" class="small-box-footer">History <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3 style="font-size:1.6rem"><?= money($totalPaid) ?></h3><p>Paid so far</p></div><div class="icon"><i class="fas fa-check"></i></div>
        <a href="rentals.php" class="small-box-footer">Details <i class="fas fa-arrow-circle-right"></i></a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box <?= $balance > 0 ? 'bg-warning' : 'bg-light' ?>"><div class="inner"><h3 style="font-size:1.6rem"><?= money($balance) ?></h3><p>Balance due</p></div><div class="icon"><i class="fas fa-balance-scale"></i></div>
        <a href="rentals.php" class="small-box-footer">Pay now <i class="fas fa-arrow-circle-right"></i></a></div></div>
    </div>
    <div class="card"><div class="card-header"><h3 class="card-title">My recent rentals</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover">
          <thead><tr><th>ID</th><th>Car</th><th>Period</th><th class="text-right">Total</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($mine, 0, 5) as $r): ?>
            <tr><td><?= $r['rental_id'] ?></td><td><?= e($r['plate_number']) ?> <small class="text-muted"><?= e($r['make'] . ' ' . $r['model']) ?></small></td>
                <td><?= e($r['rental_date']) ?> &rarr; <?= e($r['return_date']) ?></td><td class="text-right"><?= money($r['total_amount']) ?></td><td><?= rentalBadge($r) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$mine): ?><tr><td colspan="5" class="text-center text-muted p-4">You have not rented a car yet. <a href="cars.php">Browse cars</a></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div></div>
    <?php
}
layout_end();
