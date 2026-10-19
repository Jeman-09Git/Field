<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([(int)$_POST['customer_id']]);
        flash('success', 'Customer deleted.');
    } catch (PDOException $ex) {
        flash('danger', 'This customer has rental records and cannot be deleted.');
    }
    redirect('customers.php');
}

$pageTitle = 'Customers';
$active    = 'customers';
$crumbs    = [['Home', 'index.php'], ['Customers', null]];
require __DIR__ . '/includes/header.php';

$q = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, COUNT(r.rental_id) AS rentals,
               SUM(CASE WHEN r.status='Ongoing' THEN 1 ELSE 0 END) AS ongoing
        FROM customers c LEFT JOIN rentals r ON r.customer_id = c.customer_id WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.license_number LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$sql .= " GROUP BY c.customer_id ORDER BY c.full_name";
$st = $pdo->prepare($sql);
$st->execute($params);
$customers = $st->fetchAll();
?>
<div class="card card-outline card-primary">
  <div class="card-body">
    <form method="get" class="form-row align-items-end">
      <div class="form-group col-md-8">
        <label for="q">Search</label>
        <div class="input-group">
          <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
          <input id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Name, phone, email or licence number">
        </div>
      </div>
      <div class="form-group col-md-4"><button class="btn btn-primary"><i class="fas fa-search"></i> Search</button> <a href="customers.php" class="btn btn-default">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title"><?= count($customers) ?> customer(s)</h3>
    <div class="card-tools"><a href="customer_form.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add customer</a></div></div>
  <div class="card-body table-responsive p-0">
    <table class="table table-hover text-nowrap">
      <thead><tr><th>ID</th><th>Full name</th><th>Phone</th><th>Email</th><th>Licence no.</th><th>Rentals</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><?= $c['customer_id'] ?></td>
          <td><b><?= e($c['full_name']) ?></b></td>
          <td><?= e($c['phone']) ?></td>
          <td><?= e($c['email']) ?></td>
          <td><?= e($c['license_number']) ?></td>
          <td><span class="badge badge-info"><?= $c['rentals'] ?></span>
              <?php if ($c['ongoing']): ?><span class="badge badge-primary"><?= $c['ongoing'] ?> ongoing</span><?php endif; ?></td>
          <td><div class="btn-group btn-group-sm">
            <a href="customer_view.php?id=<?= $c['customer_id'] ?>" class="btn btn-info" title="View"><i class="fas fa-eye"></i></a>
            <a href="customer_form.php?id=<?= $c['customer_id'] ?>" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
            <button type="button" class="btn btn-danger js-delete" data-id="<?= $c['customer_id'] ?>" data-name="<?= e($c['full_name']) ?>" title="Delete"><i class="fas fa-trash"></i></button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="7" class="text-center text-muted p-4">No customers found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><form method="post" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Delete customer</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">Delete customer <b id="delName"></b>? This cannot be undone.</div>
    <input type="hidden" name="action" value="delete"><input type="hidden" name="customer_id" id="delId">
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Back</button><button class="btn btn-danger">Delete</button></div>
  </form></div>
</div>
<?php
$pageScript = <<<'JS'
<script>
$('.js-delete').on('click', function () {
  $('#delId').val($(this).data('id')); $('#delName').text($(this).data('name')); $('#deleteModal').modal('show');
});
</script>
JS;
require __DIR__ . '/includes/footer.php';
