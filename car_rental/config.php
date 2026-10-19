<?php
/*
 * Car Rental System - shared setup used by every page:
 * database connection, login/session helpers, security (CSRF), and the AdminLTE page layout.
 */
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

/* ---------- settings (change these if needed) ---------- */
$DB_HOST = 'localhost';
$DB_NAME = 'car_rentals';
$DB_USER = 'root';
$DB_PASS = '';                       // XAMPP default is an empty password

// Staff (admin) account, created automatically the first time the site runs.
const ADMIN_NAME     = 'Administrator';
const ADMIN_EMAIL    = 'admin@carrental.com';
const ADMIN_PASSWORD = 'admin123';

/* ---------- database ---------- */
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $ex) {
    die('<h3>Database connection failed</h3><p>Start Apache and MySQL in XAMPP, make sure the database '
        . '<b>car_rentals</b> exists, and check the settings at the top of config.php.</p><pre>'
        . htmlspecialchars($ex->getMessage()) . '</pre>');
}

// Make sure the users table exists, and create the staff account if there is none.
$usersTable = "CREATE TABLE IF NOT EXISTS users (
    user_id       INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT(11) NULL UNIQUE,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    %s
) ENGINE=InnoDB";
try {
    $pdo->exec(sprintf($usersTable, ',FOREIGN KEY (customer_id) REFERENCES customers(customer_id)'));
} catch (PDOException $ex) {
    $pdo->exec(sprintf($usersTable, ''));   // fall back without the foreign key
}
if (!(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()) {
    $pdo->prepare("INSERT IGNORE INTO users (full_name, email, password_hash, role) VALUES (?,?,?, 'admin')")
        ->execute([ADMIN_NAME, ADMIN_EMAIL, password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
}

/* SQL fragment: total of the "Paid" payments of rental r */
const PAID_SQL = "COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.rental_id = r.rental_id AND p.payment_status = 'Paid'), 0)";

/* ---------- small helpers ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return 'TZS ' . number_format((float)$n, 0); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function rentalDays(string $a, string $b): int { return max(1, (int)(new DateTime($a))->diff(new DateTime($b))->days); }

function carBadge(string $s): string {
    $m = ['Available' => 'success', 'Rented' => 'warning', 'Under Maintenance' => 'danger'];
    return '<span class="badge badge-' . ($m[$s] ?? 'secondary') . '">' . e($s) . '</span>';
}
function rentalBadge(array $r): string {
    if ($r['status'] === 'Ongoing' && $r['return_date'] < date('Y-m-d')) return '<span class="badge badge-danger">Overdue</span>';
    $m = ['Ongoing' => 'primary', 'Completed' => 'success', 'Cancelled' => 'secondary'];
    return '<span class="badge badge-' . ($m[$r['status']] ?? 'secondary') . '">' . e($r['status']) . '</span>';
}
function paymentBadge(string $s): string {
    $m = ['Paid' => 'success', 'Pending' => 'warning', 'Refunded' => 'info'];
    return '<span class="badge badge-' . ($m[$s] ?? 'secondary') . '">' . e($s) . '</span>';
}

/* ---------- security: CSRF token on every form ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    die('This form has expired. Go back, refresh the page and try again.');
}

/* ---------- login helpers ---------- */
function user(): ?array { return $_SESSION['user'] ?? null; }
function remember_user(array $row): void {
    $_SESSION['user'] = ['user_id' => (int)$row['user_id'], 'customer_id' => $row['customer_id'] ? (int)$row['customer_id'] : null,
                         'full_name' => $row['full_name'], 'email' => $row['email'], 'role' => $row['role']];
}
/** Called once at sign-in / sign-up: new session id, then remember who the user is. */
function login_user(array $row): void {
    session_regenerate_id(true);
    remember_user($row);
}
/** Every protected page calls this first. Re-reads the user so deleted or changed accounts take effect at once. */
function require_login(): array {
    global $pdo;
    $u = user();
    if ($u) {
        $st = $pdo->prepare("SELECT user_id, customer_id, full_name, email, role FROM users WHERE user_id = ?");
        $st->execute([$u['user_id']]);
        if ($row = $st->fetch()) { remember_user($row); return user(); }
        $_SESSION['user'] = null;
    }
    redirect('login.php');
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { flash('danger', 'That page is for staff only.'); redirect('index.php'); }
    return $u;
}

/* ---------- page layout (AdminLTE 3) ---------- */
function layout_start(string $title, string $active, array $crumbs): void {
    $u = user();
    $admin = $u['role'] === 'admin';
    $menu = $admin ? [
        'dashboard'   => ['index.php',                    'fas fa-tachometer-alt',  'Dashboard'],
        'cars'        => ['cars.php',                     'fas fa-car',             'Cars'],
        'rentals'     => ['rentals.php',                  'fas fa-key',             'Rentals'],
        'customers'   => ['admin.php?tab=customers',      'fas fa-users',           'Customers'],
        'categories'  => ['admin.php?tab=categories',     'fas fa-tags',            'Categories'],
        'payments'    => ['admin.php?tab=payments',       'fas fa-money-bill-wave', 'Payments'],
        'maintenance' => ['admin.php?tab=maintenance',    'fas fa-tools',           'Maintenance'],
    ] : [
        'dashboard' => ['index.php',   'fas fa-home',          'My dashboard'],
        'cars'      => ['cars.php',    'fas fa-car',           'Rent a car'],
        'rentals'   => ['rentals.php', 'fas fa-key',           'My rentals'],
    ];
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | Car Rental System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
    </ul>
    <ul class="navbar-nav ml-auto align-items-center">
      <li class="nav-item d-none d-sm-block mr-3 text-muted"><i class="far fa-user"></i> <?= e($u['full_name']) ?>
        <span class="badge badge-<?= $admin ? 'danger' : 'info' ?>"><?= $admin ? 'Staff' : 'Customer' ?></span></li>
      <li class="nav-item mr-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#pwModal"><i class="fas fa-lock"></i> Password</button>
      </li>
      <li class="nav-item">
        <form method="post" action="login.php" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="logout">
          <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-sign-out-alt"></i> Sign out</button></form>
      </li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link"><i class="fas fa-car-side brand-image img-circle elevation-3" style="line-height:33px;text-align:center;width:33px;background:#007bff"></i>
      <span class="brand-text font-weight-light">Car Rental System</span></a>
    <div class="sidebar">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
          <?php foreach ($menu as $key => [$url, $icon, $label]): ?>
            <li class="nav-item"><a href="<?= $url ?>" class="nav-link <?= $active === $key ? 'active' : '' ?>">
              <i class="nav-icon <?= $icon ?>"></i><p><?= $label ?></p></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><div class="row mb-2">
      <div class="col-sm-6"><h1><?= e($title) ?></h1></div>
      <div class="col-sm-6"><ol class="breadcrumb float-sm-right">
        <?php foreach ($crumbs as $i => [$label, $url]): ?>
          <?php if ($url && $i < count($crumbs) - 1): ?><li class="breadcrumb-item"><a href="<?= $url ?>"><?= e($label) ?></a></li>
          <?php else: ?><li class="breadcrumb-item active"><?= e($label) ?></li><?php endif; ?>
        <?php endforeach; ?>
      </ol></div>
    </div></div></section>
    <section class="content"><div class="container-fluid">
      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash[0]) ?> alert-dismissible">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          <i class="icon fas <?= $flash[0] === 'success' ? 'fa-check' : 'fa-ban' ?>"></i> <?= e($flash[1]) ?>
        </div>
      <?php endif; ?>
<?php
}

function layout_end(string $script = ''): void {
    ?>
    </div></section>
  </div>
  <footer class="main-footer"><strong>Car Rental System</strong> &mdash; AdminLTE 3 independent project.</footer>
</div>

<!-- Shared confirmation modal (delete / cancel / return) -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><form method="post" class="modal-content">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="cfAction"><input type="hidden" name="id" id="cfId">
    <div class="modal-header"><h5 class="modal-title" id="cfTitle">Confirm</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
    <div class="modal-body" id="cfText"></div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Back</button>
      <button class="btn btn-danger" id="cfBtn">Confirm</button></div>
  </form></div>
</div>

<!-- Change-password modal (opened from the top bar) -->
<div class="modal fade" id="pwModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><form method="post" action="login.php" class="modal-content">
    <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
    <div class="modal-header"><h5 class="modal-title">Change my password</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
    <div class="modal-body">
      <div class="form-group"><label>Current password</label><input type="password" class="form-control" name="current_password" autocomplete="current-password" required></div>
      <div class="form-row">
        <div class="form-group col-6"><label>New password <small class="text-muted">(6+)</small></label><input type="password" class="form-control" name="new_password" minlength="6" autocomplete="new-password" required></div>
        <div class="form-group col-6"><label>Confirm new password</label><input type="password" class="form-control" name="confirm" minlength="6" autocomplete="new-password" required></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save"></i> Change password</button></div>
  </form></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script>
// Open a modal empty (Add buttons)
$(document).on('click', '.js-new', function () {
  var $m = $($(this).data('target'));
  $m.find('form')[0].reset();
  $m.modal('show');
});
// Open a modal filled with a row of data (Edit / Rent / Pay buttons)
$(document).on('click', '.js-edit', function () {
  var $m = $($(this).data('target')), d = $(this).data('row') || {};
  $m.find('form')[0].reset();
  $.each(d, function (k, v) { $m.find('[name="' + k + '"]').val(v === null ? '' : v); });
  $m.find('[data-fill]').each(function () { $(this).text(d[$(this).data('fill')]); });
  $m.data('rowData', d);
  $m.modal('show');
});
// Ask for confirmation before a delete / cancel / return
$(document).on('click', '.js-confirm', function () {
  var b = $(this);
  $('#cfAction').val(b.data('action'));
  $('#cfId').val(b.data('id'));
  $('#cfTitle').text(b.data('title'));
  $('#cfText').text(b.data('text'));
  $('#cfBtn').attr('class', 'btn btn-' + (b.data('btn') || 'danger')).text(b.data('label') || 'Confirm');
  $('#confirmModal').modal('show');
});
</script>
<?= $script ?>
</body>
</html>
<?php
}
