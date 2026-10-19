<?php
/* Staff area: customers, categories, payments and maintenance, as tabs of one page. */
require_once __DIR__ . '/config.php';
$me = require_admin();

$tabs = [
    'customers'   => ['Customers',   'fas fa-users'],
    'categories'  => ['Categories',  'fas fa-tags'],
    'payments'    => ['Payments',    'fas fa-money-bill-wave'],
    'maintenance' => ['Maintenance', 'fas fa-tools'],
];
$tab = $_GET['tab'] ?? 'customers';
if (!isset($tabs[$tab])) $tab = 'customers';

/* ================= actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $go = 'customers';

    /* ---- customers (and their logins) ---- */
    if ($action === 'save_customer') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $name = trim($_POST['full_name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $email = trim($_POST['email'] ?? '');
        $lic = trim($_POST['license_number'] ?? ''); $addr = trim($_POST['address'] ?? ''); $pass = $_POST['password'] ?? '';
        $st = $pdo->prepare("SELECT user_id FROM users WHERE customer_id = ?");
        $st->execute([$id]);
        $userId = $id ? $st->fetchColumn() : false;

        if ($name === '' || $phone === '' || $lic === '') {
            flash('danger', 'Full name, phone and licence number are required.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Enter a valid email address.');
        } elseif (($userId || $pass !== '') && $email === '') {
            flash('danger', 'A customer with a login needs an email address.');
        } elseif ($pass !== '' && strlen($pass) < 6) {
            flash('danger', 'The password must be at least 6 characters.');
        } else {
            try {
                $pdo->beginTransaction();
                if ($id) {
                    $pdo->prepare("UPDATE customers SET full_name=?, phone=?, email=?, license_number=?, address=? WHERE customer_id=?")
                        ->execute([$name, $phone, $email ?: null, $lic, $addr ?: null, $id]);
                } else {
                    $pdo->prepare("INSERT INTO customers (full_name, phone, email, license_number, address) VALUES (?,?,?,?,?)")
                        ->execute([$name, $phone, $email ?: null, $lic, $addr ?: null]);
                    $id = (int)$pdo->lastInsertId();
                }
                if ($userId) {
                    $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE user_id=?")->execute([$name, $email, $userId]);
                    if ($pass !== '') $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([password_hash($pass, PASSWORD_DEFAULT), $userId]);
                } elseif ($pass !== '') {
                    $pdo->prepare("INSERT INTO users (customer_id, full_name, email, password_hash, role) VALUES (?,?,?,?, 'customer')")
                        ->execute([$id, $name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                }
                $pdo->commit();
                flash('success', 'Customer saved.');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Could not save. That email may already belong to another account.');
            }
        }
    }
    if ($action === 'delete_customer') {
        $id = (int)$_POST['id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM users WHERE customer_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$id]);
            $pdo->commit();
            flash('success', 'Customer deleted.');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', 'This customer has rental records and cannot be deleted.');
        }
    }

    /* ---- categories ---- */
    if ($action === 'save_category') {
        $go = 'categories';
        $id = (int)($_POST['category_id'] ?? 0); $name = trim($_POST['category_name'] ?? ''); $rate = (float)($_POST['daily_rate'] ?? 0);
        if ($name === '' || $rate <= 0) flash('danger', 'Enter a category name and a daily rate greater than 0.');
        elseif ($id) { $pdo->prepare("UPDATE categories SET category_name=?, daily_rate=? WHERE category_id=?")->execute([$name, $rate, $id]); flash('success', 'Category updated.'); }
        else { $pdo->prepare("INSERT INTO categories (category_name, daily_rate) VALUES (?,?)")->execute([$name, $rate]); flash('success', 'Category added.'); }
    }
    if ($action === 'delete_category') {
        $go = 'categories';
        try { $pdo->prepare("DELETE FROM categories WHERE category_id=?")->execute([(int)$_POST['id']]); flash('success', 'Category deleted.'); }
        catch (PDOException $ex) { flash('danger', 'This category is used by cars and cannot be deleted.'); }
    }

    /* ---- payments ---- */
    if ($action === 'add_payment') {
        $go = 'payments';
        $rid = (int)($_POST['rental_id'] ?? 0); $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['payment_method'] ?? ''; $pst = $_POST['payment_status'] ?? ''; $date = $_POST['payment_date'] ?? date('Y-m-d');
        if (!$rid || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($method, ['Cash', 'Card', 'Mobile Money'], true) || !in_array($pst, ['Paid', 'Pending', 'Refunded'], true)) {
            flash('danger', 'Choose a rental, enter an amount above 0, and pick a method and status.');
        } else {
            $pdo->prepare("INSERT INTO payments (rental_id, amount, payment_date, payment_method, payment_status) VALUES (?,?,?,?,?)")->execute([$rid, $amount, $date, $method, $pst]);
            flash('success', 'Payment recorded.');
        }
    }
    if ($action === 'payment_status') {
        $go = 'payments';
        $s = $_POST['payment_status'] ?? '';
        if (in_array($s, ['Paid', 'Pending', 'Refunded'], true)) {
            $pdo->prepare("UPDATE payments SET payment_status=? WHERE payment_id=?")->execute([$s, (int)$_POST['payment_id']]);
            flash('success', 'Payment status updated.');
        }
    }

    /* ---- maintenance ---- */
    if ($action === 'add_maintenance') {
        $go = 'maintenance';
        $carId = (int)($_POST['car_id'] ?? 0); $date = $_POST['maintenance_date'] ?? date('Y-m-d');
        $desc = trim($_POST['description'] ?? ''); $cost = (float)($_POST['cost'] ?? 0); $block = !empty($_POST['block_car']);
        $st = $pdo->prepare("SELECT status FROM cars WHERE car_id=?");
        $st->execute([$carId]);
        $carStatus = $st->fetchColumn();
        if (!$carId || $desc === '' || $cost < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) flash('danger', 'Choose a car, describe the work, and enter a cost of 0 or more.');
        elseif ($block && $carStatus === 'Rented') flash('danger', 'This car is rented out right now. Wait until it is returned before sending it to maintenance.');
        else {
            $pdo->prepare("INSERT INTO maintenance (car_id, maintenance_date, description, cost) VALUES (?,?,?,?)")->execute([$carId, $date, $desc, $cost]);
            if ($block) $pdo->prepare("UPDATE cars SET status='Under Maintenance' WHERE car_id=?")->execute([$carId]);
            flash('success', 'Maintenance record saved.');
        }
    }
    if ($action === 'car_available') {
        $go = 'maintenance';
        $pdo->prepare("UPDATE cars SET status='Available' WHERE car_id=? AND status='Under Maintenance'")->execute([(int)$_POST['id']]);
        flash('success', 'The car is available again.');
    }
    redirect('admin.php?tab=' . $go);
}

/* ================= page ================= */
[$title] = $tabs[$tab];
layout_start($title, $tab, [['Home', 'index.php'], [$title, null]]);
?>

<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $key => [$label, $icon]): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="admin.php?tab=<?= $key ?>"><i class="<?= $icon ?>"></i> <?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'customers'):
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT c.*, u.user_id, (SELECT COUNT(*) FROM rentals r WHERE r.customer_id = c.customer_id) AS rentals
            FROM customers c LEFT JOIN users u ON u.customer_id = c.customer_id WHERE 1=1";
    $params = [];
    if ($q !== '') { $sql .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.license_number LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
    $st = $pdo->prepare($sql . " ORDER BY c.full_name");
    $st->execute($params);
    $rows = $st->fetchAll(); ?>
  <div class="card card-outline card-primary"><div class="card-body">
    <form method="get" class="form-row align-items-end"><input type="hidden" name="tab" value="customers">
      <div class="form-group col-md-8"><label for="q">Search</label>
        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
          <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, phone, email or licence number"></div></div>
      <div class="form-group col-md-4"><button class="btn btn-primary"><i class="fas fa-search"></i> Search</button> <a href="admin.php?tab=customers" class="btn btn-default">Reset</a></div>
    </form></div></div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><?= count($rows) ?> customer(s)</h3>
      <div class="card-tools"><button class="btn btn-primary btn-sm js-new" data-target="#custModal"><i class="fas fa-user-plus"></i> Add customer</button></div></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-hover">
        <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Licence no.</th><th>Rentals</th><th>Login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
          <tr><td><?= $c['customer_id'] ?></td><td><b><?= e($c['full_name']) ?></b></td><td><?= e($c['phone']) ?></td><td><?= e($c['email']) ?></td><td><?= e($c['license_number']) ?></td>
              <td><span class="badge badge-info"><?= $c['rentals'] ?></span></td>
              <td><?= $c['user_id'] ? '<span class="badge badge-success">Has login</span>' : '<span class="badge badge-secondary">No login</span>' ?></td>
              <td><div class="btn-group btn-group-sm">
                <button class="btn btn-info js-edit" title="Edit / set password" data-target="#custModal" data-row="<?= e(json_encode(array_intersect_key($c, array_flip(['customer_id', 'full_name', 'phone', 'email', 'license_number', 'address'])))) ?>"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger js-confirm" title="Delete" data-action="delete_customer" data-id="<?= $c['customer_id'] ?>" data-title="Delete customer" data-text="Delete <?= e($c['full_name']) ?> and their login? This cannot be undone." data-label="Delete"><i class="fas fa-trash"></i></button>
              </div></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted p-4">No customers found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="modal fade" id="custModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" value="0">
      <div class="modal-header"><h5 class="modal-title">Customer details</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>Full name *</label><input class="form-control" name="full_name" required></div>
        <div class="form-row">
          <div class="form-group col-6"><label>Phone *</label><input class="form-control" name="phone" required></div>
          <div class="form-group col-6"><label>Driver's licence no. *</label><input class="form-control" name="license_number" required></div>
        </div>
        <div class="form-group"><label>Email <small class="text-muted">(the customer's login)</small></label><input type="email" class="form-control" name="email"></div>
        <div class="form-group"><label>Address</label><input class="form-control" name="address"></div>
        <div class="form-group"><label>Password</label><input type="password" class="form-control" name="password" minlength="6" autocomplete="new-password">
          <small class="text-muted">Leave blank to keep the current password. Enter one to create or reset the customer's login (needs an email).</small></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
    </form></div>
  </div>

<?php elseif ($tab === 'categories'):
    $rows = $pdo->query("SELECT cat.*, COUNT(ca.car_id) AS cars FROM categories cat LEFT JOIN cars ca ON ca.category_id = cat.category_id
                         GROUP BY cat.category_id ORDER BY cat.category_name")->fetchAll(); ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><?= count($rows) ?> categories</h3>
      <div class="card-tools"><button class="btn btn-primary btn-sm js-new" data-target="#catModal"><i class="fas fa-plus"></i> Add category</button></div></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-hover">
        <thead><tr><th>ID</th><th>Name</th><th class="text-right">Daily rate</th><th>Cars</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
          <tr><td><?= $c['category_id'] ?></td><td><?= e($c['category_name']) ?></td><td class="text-right"><?= money($c['daily_rate']) ?></td><td><span class="badge badge-info"><?= $c['cars'] ?></span></td>
              <td><div class="btn-group btn-group-sm">
                <button class="btn btn-info js-edit" data-target="#catModal" data-row="<?= e(json_encode(['category_id' => $c['category_id'], 'category_name' => $c['category_name'], 'daily_rate' => $c['daily_rate']])) ?>"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger js-confirm" data-action="delete_category" data-id="<?= $c['category_id'] ?>" data-title="Delete category" data-text="Delete <?= e($c['category_name']) ?>?" data-label="Delete"><i class="fas fa-trash"></i></button>
              </div></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="modal fade" id="catModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="category_id" value="0">
      <div class="modal-header"><h5 class="modal-title">Category</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>Category name *</label><input class="form-control" name="category_name" placeholder="e.g. SUV" required></div>
        <div class="form-group"><label>Daily rate *</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
          <input type="number" min="1" step="1000" class="form-control" name="daily_rate" required></div></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
    </form></div>
  </div>

<?php elseif ($tab === 'payments'):
    $fs = $_GET['status'] ?? ''; $fm = $_GET['method'] ?? '';
    $sql = "SELECT p.*, c.full_name, ca.plate_number FROM payments p JOIN rentals r ON r.rental_id = p.rental_id
            JOIN customers c ON c.customer_id = r.customer_id JOIN cars ca ON ca.car_id = r.car_id WHERE 1=1";
    $params = [];
    if (in_array($fs, ['Paid', 'Pending', 'Refunded'], true)) { $sql .= " AND p.payment_status = ?"; $params[] = $fs; }
    if (in_array($fm, ['Cash', 'Card', 'Mobile Money'], true)) { $sql .= " AND p.payment_method = ?"; $params[] = $fm; }
    $st = $pdo->prepare($sql . " ORDER BY p.payment_id DESC");
    $st->execute($params);
    $rows = $st->fetchAll();
    $totalPaid = 0; foreach ($rows as $p) if ($p['payment_status'] === 'Paid') $totalPaid += $p['amount'];
    $rentalList = $pdo->query("SELECT r.rental_id, r.total_amount, c.full_name, ca.plate_number FROM rentals r
                               JOIN customers c ON c.customer_id = r.customer_id JOIN cars ca ON ca.car_id = r.car_id
                               WHERE r.status <> 'Cancelled' ORDER BY r.rental_id DESC")->fetchAll(); ?>
  <div class="card card-outline card-primary"><div class="card-body">
    <form method="get" class="form-row align-items-end"><input type="hidden" name="tab" value="payments">
      <div class="form-group col-md-4"><label for="fs">Status</label><select id="fs" name="status" class="form-control"><option value="">All</option>
        <?php foreach (['Paid', 'Pending', 'Refunded'] as $s): ?><option <?= $fs === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
      <div class="form-group col-md-4"><label for="fm">Method</label><select id="fm" name="method" class="form-control"><option value="">All</option>
        <?php foreach (['Cash', 'Card', 'Mobile Money'] as $s): ?><option <?= $fm === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
      <div class="form-group col-md-4"><button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <a href="admin.php?tab=payments" class="btn btn-default">Reset</a></div>
    </form></div></div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><?= count($rows) ?> payment(s) &middot; paid total <b><?= money($totalPaid) ?></b></h3>
      <div class="card-tools"><button class="btn btn-primary btn-sm js-new" data-target="#payAddModal"><i class="fas fa-plus"></i> Record payment</button></div></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-hover">
        <thead><tr><th>ID</th><th>Rental</th><th>Customer</th><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Status</th><th>Change</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
          <tr><td><?= $p['payment_id'] ?></td><td>#<?= $p['rental_id'] ?> <small class="text-muted"><?= e($p['plate_number']) ?></small></td><td><?= e($p['full_name']) ?></td>
              <td class="text-nowrap"><?= e($p['payment_date']) ?></td><td><?= e($p['payment_method']) ?></td>
              <td class="text-right text-nowrap"><?= money($p['amount']) ?></td><td><?= paymentBadge($p['payment_status']) ?></td>
              <td><form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="payment_status"><input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                <div class="btn-group"><button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown">Set</button>
                  <div class="dropdown-menu"><?php foreach (['Paid', 'Pending', 'Refunded'] as $s): ?><button class="dropdown-item" name="payment_status" value="<?= $s ?>"><?= $s ?></button><?php endforeach; ?></div></div></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted p-4">No payments found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="modal fade" id="payAddModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_payment">
      <div class="modal-header"><h5 class="modal-title">Record payment</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>Rental *</label><select class="form-control" name="rental_id" required><option value="">Select rental...</option>
          <?php foreach ($rentalList as $r): ?><option value="<?= $r['rental_id'] ?>">#<?= $r['rental_id'] ?> - <?= e($r['full_name']) ?> (<?= e($r['plate_number']) ?>, <?= money($r['total_amount']) ?>)</option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Amount *</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
          <input type="number" min="1" step="1000" class="form-control" name="amount" required></div></div>
        <div class="form-group"><label>Payment date</label><input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-row">
          <div class="form-group col-6"><label>Method</label><select class="form-control" name="payment_method"><option>Cash</option><option>Card</option><option>Mobile Money</option></select></div>
          <div class="form-group col-6"><label>Status</label><select class="form-control" name="payment_status"><option>Paid</option><option>Pending</option><option>Refunded</option></select></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save"></i> Save payment</button></div>
    </form></div>
  </div>

<?php else: /* maintenance */
    $rows = $pdo->query("SELECT m.*, ca.plate_number, ca.make, ca.model, ca.status AS car_status FROM maintenance m
                         JOIN cars ca ON ca.car_id = m.car_id ORDER BY m.maintenance_date DESC, m.maintenance_id DESC")->fetchAll();
    $carList = $pdo->query("SELECT car_id, plate_number, make, model, status FROM cars ORDER BY make, model")->fetchAll();
    $totalCost = array_sum(array_column($rows, 'cost')); ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><?= count($rows) ?> record(s) &middot; total cost <b><?= money($totalCost) ?></b></h3>
      <div class="card-tools"><button class="btn btn-primary btn-sm js-new" data-target="#mtModal"><i class="fas fa-plus"></i> Log maintenance</button></div></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-hover">
        <thead><tr><th>ID</th><th>Car</th><th>Date</th><th>Work done</th><th class="text-right">Cost</th><th>Car status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $m): ?>
          <tr><td><?= $m['maintenance_id'] ?></td><td><b><?= e($m['plate_number']) ?></b> <small class="text-muted"><?= e($m['make'] . ' ' . $m['model']) ?></small></td>
              <td class="text-nowrap"><?= e($m['maintenance_date']) ?></td><td><?= e($m['description']) ?></td><td class="text-right text-nowrap"><?= money($m['cost']) ?></td>
              <td class="text-nowrap"><?= carBadge($m['car_status']) ?>
                <?php if ($m['car_status'] === 'Under Maintenance'): ?>
                  <button class="btn btn-xs btn-success js-confirm" title="Work finished" data-action="car_available" data-id="<?= $m['car_id'] ?>" data-btn="success" data-label="Make available"
                    data-title="Work finished" data-text="Make <?= e($m['plate_number']) ?> available for rent again?"><i class="fas fa-check"></i></button>
                <?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted p-4">No maintenance recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="modal fade" id="mtModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_maintenance">
      <div class="modal-header"><h5 class="modal-title">Log maintenance</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="form-group"><label>Car *</label><select class="form-control" name="car_id" required><option value="">Select car...</option>
          <?php foreach ($carList as $c): ?><option value="<?= $c['car_id'] ?>"><?= e($c['plate_number']) ?> - <?= e($c['make'] . ' ' . $c['model']) ?> (<?= e($c['status']) ?>)</option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Date *</label><input type="date" class="form-control" name="maintenance_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label>Work done *</label><input class="form-control" name="description" maxlength="255" placeholder="e.g. Oil change and brake inspection" required></div>
        <div class="form-group"><label>Cost *</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
          <input type="number" min="0" step="1000" class="form-control" name="cost" value="0" required></div></div>
        <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="block_car" name="block_car" value="1">
          <label class="custom-control-label" for="block_car">Mark the car as Under Maintenance</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save"></i> Save record</button></div>
    </form></div>
  </div>
<?php endif; ?>

<?php layout_end(); ?>
