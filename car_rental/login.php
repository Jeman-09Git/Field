<?php
/* Sign in, create account and sign out - all on one page. */
require_once __DIR__ . '/config.php';

$errors = [];
$old    = ['full_name' => '', 'phone' => '', 'email' => '', 'license_number' => '', 'address' => ''];
$tab    = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        $_SESSION['user'] = null;
        session_regenerate_id(true);
        flash('success', 'You have been signed out.');
        redirect('login.php');
    }

    if ($action === 'change_password') {
        $me = require_login();
        $st = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $st->execute([$me['user_id']]);
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', (string)$st->fetchColumn())) flash('danger', 'Your current password is wrong.');
        elseif (strlen($new) < 6)                      flash('danger', 'The new password must be at least 6 characters.');
        elseif ($new !== ($_POST['confirm'] ?? ''))    flash('danger', 'The two new passwords do not match.');
        else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $me['user_id']]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.');
        }
        redirect('index.php');
    }

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            login_user($row);
            redirect('index.php');
        }
        $errors[] = 'Wrong email or password.';
        $old['email'] = $email;
    }

    if ($action === 'register') {
        $tab = 'register';
        foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($old['full_name'] === '' || $old['phone'] === '' || $old['license_number'] === '') $errors[] = 'Full name, phone and driver\'s licence number are required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address. You will use it to sign in.';
        if (strlen($pass) < 6) $errors[] = 'The password must be at least 6 characters.';
        if ($pass !== ($_POST['confirm'] ?? '')) $errors[] = 'The two passwords do not match.';

        if (!$errors) {
            $st = $pdo->prepare("SELECT (SELECT COUNT(*) FROM users WHERE email = ?) + (SELECT COUNT(*) FROM customers WHERE email = ?)");
            $st->execute([$old['email'], $old['email']]);
            if ($st->fetchColumn() > 0) $errors[] = 'This email is already registered. Sign in instead, or ask staff to set up your login.';
        }
        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO customers (full_name, phone, email, license_number, address) VALUES (?,?,?,?,?)")
                    ->execute([$old['full_name'], $old['phone'], $old['email'], $old['license_number'], $old['address'] ?: null]);
                $cid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO users (customer_id, full_name, email, password_hash, role) VALUES (?,?,?,?, 'customer')")
                    ->execute([$cid, $old['full_name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT)]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->commit();
                login_user(['user_id' => $uid, 'customer_id' => $cid, 'full_name' => $old['full_name'], 'email' => $old['email'], 'role' => 'customer']);
                flash('success', 'Welcome, ' . $old['full_name'] . '! Your account is ready. Pick a car to rent.');
                redirect('cars.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Could not create the account. That email may already be in use.';
            }
        }
    }
}

if (user() && $_SERVER['REQUEST_METHOD'] === 'GET') redirect('index.php');

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | Car Rental System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box" style="width:460px;max-width:94%">
  <div class="login-logo"><i class="fas fa-car-side text-primary"></i> <b>Car</b> Rental</div>

  <?php if ($flash): ?><div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="card card-outline card-primary">
    <div class="card-header p-2">
      <ul class="nav nav-pills nav-fill">
        <li class="nav-item"><a class="nav-link <?= $tab === 'login' ? 'active' : '' ?>" href="#t-login" data-toggle="tab">Sign in</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'register' ? 'active' : '' ?>" href="#t-register" data-toggle="tab">Create account</a></li>
      </ul>
    </div>
    <div class="card-body">
      <div class="tab-content">

        <!-- Sign in -->
        <div class="tab-pane <?= $tab === 'login' ? 'active' : '' ?>" id="t-login">
          <p class="login-box-msg">Sign in to rent a car</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="login">
            <div class="input-group mb-3">
              <input type="email" name="email" class="form-control" placeholder="Email" value="<?= $tab === 'login' ? e($old['email']) : '' ?>" required>
              <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
            </div>
            <div class="input-group mb-3">
              <input type="password" name="password" class="form-control" placeholder="Password" required>
              <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
            </div>
            <button class="btn btn-primary btn-block">Sign in</button>
          </form>
        </div>

        <!-- Create account -->
        <div class="tab-pane <?= $tab === 'register' ? 'active' : '' ?>" id="t-register">
          <p class="login-box-msg">Create your customer account</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="register">
            <div class="form-group"><label for="full_name">Full name *</label>
              <input class="form-control" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required></div>
            <div class="form-row">
              <div class="form-group col-6"><label for="phone">Phone *</label>
                <input class="form-control" id="phone" name="phone" value="<?= e($old['phone']) ?>" required></div>
              <div class="form-group col-6"><label for="license_number">Driver's licence no. *</label>
                <input class="form-control" id="license_number" name="license_number" value="<?= e($old['license_number']) ?>" required></div>
            </div>
            <div class="form-group"><label for="reg_email">Email * <small class="text-muted">(your login)</small></label>
              <input type="email" class="form-control" id="reg_email" name="email" value="<?= e($old['email']) ?>" required></div>
            <div class="form-group"><label for="address">Address</label>
              <input class="form-control" id="address" name="address" value="<?= e($old['address']) ?>"></div>
            <div class="form-row">
              <div class="form-group col-6"><label for="password">Password * <small class="text-muted">(6+)</small></label>
                <input type="password" class="form-control" id="password" name="password" minlength="6" required></div>
              <div class="form-group col-6"><label for="confirm">Confirm password *</label>
                <input type="password" class="form-control" id="confirm" name="confirm" minlength="6" required></div>
            </div>
            <button class="btn btn-success btn-block"><i class="fas fa-user-plus"></i> Create account</button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
