<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$cu = ['full_name' => '', 'phone' => '', 'email' => '', 'license_number' => '', 'address' => ''];
if ($id) {
    $st = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $st->execute([$id]);
    $cu = $st->fetch() ?: $cu;
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($cu) as $f) { if (isset($_POST[$f])) $cu[$f] = trim($_POST[$f]); }
    if ($cu['full_name'] === '' || $cu['phone'] === '' || $cu['license_number'] === '') $errors[] = 'Full name, phone and licence number are required.';
    if ($cu['email'] !== '' && !filter_var($cu['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';

    if (!$errors) {
        try {
            if ($id) {
                $pdo->prepare("UPDATE customers SET full_name=?, phone=?, email=?, license_number=?, address=? WHERE customer_id=?")
                    ->execute([$cu['full_name'], $cu['phone'], $cu['email'], $cu['license_number'], $cu['address'], $id]);
                flash('success', 'Customer updated.');
            } else {
                $pdo->prepare("INSERT INTO customers (full_name, phone, email, license_number, address) VALUES (?,?,?,?,?)")
                    ->execute([$cu['full_name'], $cu['phone'], $cu['email'], $cu['license_number'], $cu['address']]);
                flash('success', 'Customer registered.');
            }
            redirect('customers.php');
        } catch (PDOException $ex) {
            $errors[] = 'Could not save. The email or licence number may already belong to another customer.';
        }
    }
}

$pageTitle = $id ? 'Edit customer' : 'Register customer';
$active    = 'customers';
$crumbs    = [['Home', 'index.php'], ['Customers', 'customers.php'], [$id ? 'Edit' : 'New', null]];
require __DIR__ . '/includes/header.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="row"><div class="col-lg-7">
<form method="post" class="card card-primary">
  <div class="card-header"><h3 class="card-title">Customer details</h3></div>
  <div class="card-body">
    <div class="form-group"><label for="full_name">Full name *</label>
      <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
      <input class="form-control" id="full_name" name="full_name" value="<?= e($cu['full_name']) ?>" required></div></div>
    <div class="form-row">
      <div class="form-group col-md-6"><label for="phone">Phone *</label>
        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
        <input class="form-control" id="phone" name="phone" value="<?= e($cu['phone']) ?>" required></div></div>
      <div class="form-group col-md-6"><label for="email">Email</label>
        <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
        <input type="email" class="form-control" id="email" name="email" value="<?= e($cu['email']) ?>"></div></div>
    </div>
    <div class="form-group"><label for="license_number">Driver's licence number *</label>
      <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="far fa-id-card"></i></span></div>
      <input class="form-control" id="license_number" name="license_number" value="<?= e($cu['license_number']) ?>" required></div></div>
    <div class="form-group"><label for="address">Address</label><input class="form-control" id="address" name="address" value="<?= e($cu['address']) ?>"></div>
  </div>
  <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save"></i> Save customer</button> <a href="customers.php" class="btn btn-default">Cancel</a></div>
</form>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
