<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $rid    = (int)($_POST['rental_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['payment_method'] ?? '';
        $status = $_POST['payment_status'] ?? '';
        $date   = $_POST['payment_date'] ?? date('Y-m-d');
        if (!$rid || $amount <= 0 || !in_array($method, ['Cash', 'Card', 'Mobile Money'], true)
            || !in_array($status, ['Paid', 'Pending', 'Refunded'], true)) {
            flash('danger', 'Choose a rental, a payment method and status, and enter an amount greater than 0.');
        } else {
            $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_date, payment_method, payment_status) VALUES (?,?,?,?,?)")
                ->execute([$rid, $amount, $date, $method, $status]);
            flash('success', 'Payment recorded.');
        }
    } elseif ($action === 'status') {
        $s = $_POST['payment_status'] ?? '';
        if (in_array($s, ['Paid', 'Pending', 'Refunded'], true)) {
            $pdo->prepare("UPDATE payments SET payment_status=? WHERE payment_id=?")->execute([$s, (int)$_POST['payment_id']]);
            flash('success', 'Payment status updated.');
        }
    }
    redirect('payments.php');
}

$pageTitle = 'Payments';
$active    = 'payments';
$crumbs    = [['Home', 'index.php'], ['Payments', null]];
require __DIR__ . '/includes/header.php';

$status = $_GET['status'] ?? '';
$method = $_GET['method'] ?? '';
$preRental = (int)($_GET['rental_id'] ?? 0);

$sql = "SELECT p.*, c.full_name, ca.plate_number FROM payments p
        JOIN rentals r ON r.rental_id = p.rental_id
        JOIN customers c ON c.customer_id = r.customer_id
        JOIN cars ca ON ca.car_id = r.car_id WHERE 1=1";
$params = [];
if (in_array($status, ['Paid', 'Pending', 'Refunded'], true)) { $sql .= " AND p.payment_status = ?"; $params[] = $status; }
if (in_array($method, ['Cash', 'Card', 'Mobile Money'], true)) { $sql .= " AND p.payment_method = ?"; $params[] = $method; }
$sql .= " ORDER BY p.payment_id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$payments = $st->fetchAll();

$rentals = $pdo->query("SELECT r.rental_id, r.total_amount, c.full_name, ca.plate_number
                        FROM rentals r JOIN customers c ON c.customer_id = r.customer_id
                        JOIN cars ca ON ca.car_id = r.car_id
                        WHERE r.status <> 'Cancelled' ORDER BY r.rental_id DESC")->fetchAll();
$totalPaid = 0;
foreach ($payments as $p) { if ($p['payment_status'] === 'Paid') $totalPaid += $p['amount']; }
?>
<div class="row">
  <div class="col-lg-4">
    <form method="post" class="card card-primary">
      <div class="card-header"><h3 class="card-title">Record payment</h3></div>
      <div class="card-body">
        <input type="hidden" name="action" value="add">
        <div class="form-group"><label for="rental_id">Rental *</label>
          <select class="form-control" id="rental_id" name="rental_id" required>
            <option value="">Select rental...</option>
            <?php foreach ($rentals as $r): ?>
              <option value="<?= $r['rental_id'] ?>" <?= $preRental === (int)$r['rental_id'] ? 'selected' : '' ?>>
                #<?= $r['rental_id'] ?> - <?= e($r['full_name']) ?> (<?= e($r['plate_number']) ?>, <?= money($r['total_amount']) ?>)
              </option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label for="amount">Amount *</label>
          <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
          <input type="number" min="1" step="1000" class="form-control" id="amount" name="amount" required></div></div>
        <div class="form-group"><label for="payment_date">Payment date</label>
          <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-row">
          <div class="form-group col-6"><label for="payment_method">Method</label>
            <select class="form-control" id="payment_method" name="payment_method"><option>Cash</option><option>Card</option><option>Mobile Money</option></select></div>
          <div class="form-group col-6"><label for="payment_status">Status</label>
            <select class="form-control" id="payment_status" name="payment_status"><option>Paid</option><option>Pending</option><option>Refunded</option></select></div>
        </div>
      </div>
      <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save"></i> Save payment</button></div>
    </form>
  </div>

  <div class="col-lg-8">
    <div class="card card-outline card-primary">
      <div class="card-body">
        <form method="get" class="form-row align-items-end">
          <div class="form-group col-md-4"><label for="fs">Status</label>
            <select id="fs" name="status" class="form-control"><option value="">All</option>
              <?php foreach (['Paid', 'Pending', 'Refunded'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
          <div class="form-group col-md-4"><label for="fm">Method</label>
            <select id="fm" name="method" class="form-control"><option value="">All</option>
              <?php foreach (['Cash', 'Card', 'Mobile Money'] as $s): ?><option <?= $method === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
          <div class="form-group col-md-4"><button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <a href="payments.php" class="btn btn-default">Reset</a></div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3 class="card-title"><?= count($payments) ?> payment(s) &middot; paid total <b><?= money($totalPaid) ?></b></h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
          <thead><tr><th>ID</th><th>Rental</th><th>Customer</th><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Status</th><th>Change</th></tr></thead>
          <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= $p['payment_id'] ?></td>
              <td><a href="rental_view.php?id=<?= $p['rental_id'] ?>">#<?= $p['rental_id'] ?></a> <small class="text-muted"><?= e($p['plate_number']) ?></small></td>
              <td><?= e($p['full_name']) ?></td><td><?= e($p['payment_date']) ?></td><td><?= e($p['payment_method']) ?></td>
              <td class="text-right"><?= money($p['amount']) ?></td><td><?= paymentBadge($p['payment_status']) ?></td>
              <td>
                <form method="post" class="form-inline">
                  <input type="hidden" name="action" value="status"><input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                  <div class="btn-group">
                    <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown">Set</button>
                    <div class="dropdown-menu">
                      <?php foreach (['Paid', 'Pending', 'Refunded'] as $s): ?>
                        <button class="dropdown-item" name="payment_status" value="<?= $s ?>"><?= $s ?></button>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$payments): ?><tr><td colspan="8" class="text-center text-muted p-4">No payments found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
