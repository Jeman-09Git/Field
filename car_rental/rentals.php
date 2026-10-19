<?php
require_once __DIR__ . '/config.php';
$u = require_login();
$admin = $u['role'] === 'admin';

/** Load a rental the current user is allowed to touch (customers: only their own). */
function my_rental(PDO $pdo, array $u, int $id): ?array {
    $st = $pdo->prepare("SELECT r.*, " . PAID_SQL . " AS paid FROM rentals r WHERE r.rental_id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r || ($u['role'] !== 'admin' && (int)$r['customer_id'] !== (int)$u['customer_id'])) return null;
    return $r;
}

/* ================= actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $r = my_rental($pdo, $u, (int)($_POST['rental_id'] ?? 0));
        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? '';
        if (!$r || $r['status'] === 'Cancelled') {
            flash('danger', 'You cannot pay for that rental.');
        } elseif (!in_array($method, ['Cash', 'Card', 'Mobile Money'], true) || $amount <= 0) {
            flash('danger', 'Enter an amount greater than 0 and choose a payment method.');
        } elseif ($amount > $r['total_amount'] - $r['paid'] + 0.001) {
            flash('danger', 'The amount is more than the balance due (' . money($r['total_amount'] - $r['paid']) . ').');
        } else {
            $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_date, payment_method, payment_status) VALUES (?,?,CURDATE(),?, 'Paid')")
                ->execute([$r['rental_id'], $amount, $method]);
            flash('success', 'Payment of ' . money($amount) . ' recorded for rental #' . $r['rental_id'] . '.');
        }
        redirect('rentals.php');
    }

    if (in_array($action, ['cancel', 'return'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $r = my_rental($pdo, $u, $id);
        if (!$r || $r['status'] !== 'Ongoing') {
            flash('danger', 'That rental cannot be changed.');
        } elseif ($action === 'return' && !$admin) {
            flash('danger', 'Only staff can mark a car as returned. Please hand the car back at the office.');
        } elseif (!$admin && $r['rental_date'] < date('Y-m-d')) {
            flash('danger', 'This rental has already started, so only staff can cancel it. Please contact the office.');
        } else {
            $pdo->beginTransaction();
            if ($action === 'return') {
                $pdo->prepare("UPDATE rentals SET status='Completed', actual_return_date=CURDATE() WHERE rental_id=?")->execute([$id]);
                flash('success', "Rental #$id marked as returned. The car is available again.");
            } else {
                $pdo->prepare("UPDATE rentals SET status='Cancelled' WHERE rental_id=?")->execute([$id]);
                flash('success', "Rental #$id was cancelled.");
            }
            $pdo->prepare("UPDATE cars SET status='Available' WHERE car_id=? AND status='Rented'")->execute([$r['car_id']]);
            $pdo->commit();
        }
        redirect('rentals.php');
    }
    redirect('rentals.php');
}

/* ================= page ================= */
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$today  = date('Y-m-d');

$sql = "SELECT r.*, c.full_name, ca.plate_number, ca.make, ca.model, " . PAID_SQL . " AS paid
        FROM rentals r JOIN customers c ON c.customer_id = r.customer_id JOIN cars ca ON ca.car_id = r.car_id WHERE 1=1";
$params = [];
if (!$admin) { $sql .= " AND r.customer_id = ?"; $params[] = (int)$u['customer_id']; }
if ($q !== '') {
    if ($admin) { $sql .= " AND (c.full_name LIKE ? OR ca.plate_number LIKE ?)"; array_push($params, "%$q%", "%$q%"); }
    else        { $sql .= " AND (ca.plate_number LIKE ? OR ca.make LIKE ? OR ca.model LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
}
if ($status === 'Overdue') { $sql .= " AND r.status='Ongoing' AND r.return_date < ?"; $params[] = $today; }
elseif (in_array($status, ['Ongoing', 'Completed', 'Cancelled'], true)) { $sql .= " AND r.status = ?"; $params[] = $status; }
if ($from !== '') { $sql .= " AND r.rental_date >= ?"; $params[] = $from; }
if ($to !== '')   { $sql .= " AND r.rental_date <= ?"; $params[] = $to; }
$sql .= " ORDER BY r.rental_id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rentals = $st->fetchAll();

$payments = [];
if (!$admin) {
    $st = $pdo->prepare("SELECT p.* FROM payments p JOIN rentals r ON r.rental_id = p.rental_id WHERE r.customer_id = ? ORDER BY p.payment_id DESC");
    $st->execute([(int)$u['customer_id']]);
    $payments = $st->fetchAll();
}

$title = $admin ? 'Rentals' : 'My rentals';
layout_start($title, 'rentals', [['Home', 'index.php'], [$title, null]]);
?>

<div class="card card-outline card-primary"><div class="card-body">
  <form method="get" class="form-row align-items-end">
    <div class="form-group col-md-4"><label for="q">Search</label>
      <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
        <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= $admin ? 'Customer name or plate number' : 'Car make, model or plate' ?>"></div></div>
    <div class="form-group col-md-2"><label for="status">Status</label>
      <select class="form-control" id="status" name="status"><option value="">All</option>
        <?php foreach (['Ongoing', 'Overdue', 'Completed', 'Cancelled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="form-group col-md-2"><label for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group col-md-2"><label for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="form-group col-md-2"><button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <a href="rentals.php" class="btn btn-default">Reset</a></div>
  </form>
</div></div>

<div class="card">
  <div class="card-header"><h3 class="card-title"><?= count($rentals) ?> rental(s)</h3>
    <?php if (!$admin): ?><div class="card-tools"><a href="cars.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Rent a car</a></div><?php endif; ?></div>
  <div class="card-body table-responsive p-0">
    <table class="table table-hover">
      <thead><tr><th>ID</th><?php if ($admin): ?><th>Customer</th><?php endif; ?><th>Car</th><th>Period</th><th class="text-right">Total</th><th class="text-right">Paid</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rentals as $r):
          $bal = max(0, $r['total_amount'] - $r['paid']);
          $canPay = $r['status'] !== 'Cancelled' && $bal > 0; ?>
        <tr>
          <td><?= $r['rental_id'] ?></td>
          <?php if ($admin): ?><td><?= e($r['full_name']) ?></td><?php endif; ?>
          <td><?= e($r['plate_number']) ?> <small class="text-muted"><?= e($r['make'] . ' ' . $r['model']) ?></small></td>
          <td class="text-nowrap"><?= e($r['rental_date']) ?> &rarr; <?= e($r['return_date']) ?>
            <?php if ($r['actual_return_date']): ?><br><small class="text-muted">returned <?= e($r['actual_return_date']) ?></small><?php endif; ?></td>
          <td class="text-right text-nowrap"><?= money($r['total_amount']) ?></td>
          <td class="text-right text-nowrap"><?= money($r['paid']) ?>
            <?php if ($canPay): ?><br><small class="text-danger">due <?= money($bal) ?></small><?php endif; ?></td>
          <td><?= rentalBadge($r) ?></td>
          <td><div class="btn-group btn-group-sm">
            <?php if ($canPay): ?>
              <button class="btn btn-success js-edit" title="Pay" data-target="#payModal"
                data-row="<?= e(json_encode(['rental_id' => $r['rental_id'], 'amount' => $bal, 'max' => $bal, 'balance' => money($bal)])) ?>"><i class="fas fa-credit-card"></i></button>
            <?php endif; ?>
            <?php if ($admin && $r['status'] === 'Ongoing'): ?>
              <button class="btn btn-info js-confirm" title="Mark returned" data-action="return" data-id="<?= $r['rental_id'] ?>" data-btn="success" data-label="Mark returned"
                data-title="Return rental #<?= $r['rental_id'] ?>" data-text="The return date is set to today and the car becomes available again."><i class="fas fa-undo"></i></button>
            <?php endif; ?>
            <?php if ($r['status'] === 'Ongoing' && ($admin || $r['rental_date'] >= $today)): ?>
              <button class="btn btn-danger js-confirm" title="Cancel" data-action="cancel" data-id="<?= $r['rental_id'] ?>" data-label="Cancel rental"
                data-title="Cancel rental #<?= $r['rental_id'] ?>" data-text="The rental is cancelled and the car becomes available again."><i class="fas fa-times"></i></button>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rentals): ?><tr><td colspan="<?= $admin ? 8 : 7 ?>" class="text-center text-muted p-4">No rentals found. <?php if (!$admin): ?><a href="cars.php">Rent a car</a><?php endif; ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!$admin): ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title">My payment history</h3></div>
    <div class="card-body table-responsive p-0">
      <table class="table">
        <thead><tr><th>ID</th><th>Rental</th><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr><td><?= $p['payment_id'] ?></td><td>#<?= $p['rental_id'] ?></td><td><?= e($p['payment_date']) ?></td><td><?= e($p['payment_method']) ?></td>
              <td class="text-right"><?= money($p['amount']) ?></td><td><?= paymentBadge($p['payment_status']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-muted p-3">No payments yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Pay modal -->
<div class="modal fade" id="payModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><form method="post" class="modal-content">
    <?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="rental_id" value="0">
    <div class="modal-header"><h5 class="modal-title">Make a payment</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <p>Balance due: <b data-fill="balance"></b></p>
      <div class="form-group"><label>Amount *</label>
        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
          <input type="number" min="1" step="any" class="form-control" name="amount" required></div></div>
      <div class="form-group"><label>Payment method</label>
        <select class="form-control" name="method"><option>Mobile Money</option><option>Card</option><option>Cash</option></select></div>
      <small class="text-muted">Payments are recorded in the system only; no real money is charged.</small>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-success"><i class="fas fa-check"></i> Pay</button></div>
  </form></div>
</div>

<?php
layout_end(<<<'JS'
<script>
// the amount cannot be more than the balance
$('#payModal').on('shown.bs.modal', function () {
  var d = $(this).data('rowData') || {};
  $(this).find('[name=amount]').attr('max', d.max);
});
</script>
JS);
