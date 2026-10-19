<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id   = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['category_name'] ?? '');
            $rate = (float)($_POST['daily_rate'] ?? 0);
            if ($name === '' || $rate <= 0) {
                flash('danger', 'Enter a category name and a daily rate greater than 0.');
            } elseif ($id) {
                $pdo->prepare("UPDATE categories SET category_name=?, daily_rate=? WHERE category_id=?")->execute([$name, $rate, $id]);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare("INSERT INTO categories (category_name, daily_rate) VALUES (?,?)")->execute([$name, $rate]);
                flash('success', 'Category added.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM categories WHERE category_id=?")->execute([(int)$_POST['category_id']]);
            flash('success', 'Category deleted.');
        }
    } catch (PDOException $ex) {
        flash('danger', 'This category is used by cars and cannot be deleted.');
    }
    redirect('categories.php');
}

$pageTitle = 'Categories';
$active    = 'categories';
$crumbs    = [['Home', 'index.php'], ['Categories', null]];
require __DIR__ . '/includes/header.php';

$cats = $pdo->query("SELECT cat.*, COUNT(ca.car_id) AS cars FROM categories cat
                     LEFT JOIN cars ca ON ca.category_id = cat.category_id
                     GROUP BY cat.category_id ORDER BY cat.category_name")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$edit = ['category_id' => 0, 'category_name' => '', 'daily_rate' => ''];
foreach ($cats as $c) { if ($c['category_id'] == $editId) $edit = $c; }
?>
<div class="row">
  <div class="col-lg-5">
    <form method="post" class="card <?= $edit['category_id'] ? 'card-warning' : 'card-primary' ?>">
      <div class="card-header"><h3 class="card-title"><?= $edit['category_id'] ? 'Edit category' : 'Add category' ?></h3></div>
      <div class="card-body">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="category_id" value="<?= (int)$edit['category_id'] ?>">
        <div class="form-group"><label for="category_name">Category name *</label>
          <input class="form-control" id="category_name" name="category_name" value="<?= e($edit['category_name']) ?>" placeholder="e.g. SUV" required></div>
        <div class="form-group"><label for="daily_rate">Daily rate *</label>
          <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
            <input type="number" min="1" step="1000" class="form-control" id="daily_rate" name="daily_rate" value="<?= e($edit['daily_rate']) ?>" required></div></div>
      </div>
      <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        <?php if ($edit['category_id']): ?><a href="categories.php" class="btn btn-default">Cancel</a><?php endif; ?></div>
    </form>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><h3 class="card-title"><?= count($cats) ?> categories</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover">
          <thead><tr><th>ID</th><th>Name</th><th class="text-right">Daily rate</th><th>Cars</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($cats as $c): ?>
            <tr>
              <td><?= $c['category_id'] ?></td><td><?= e($c['category_name']) ?></td>
              <td class="text-right"><?= money($c['daily_rate']) ?></td>
              <td><span class="badge badge-info"><?= $c['cars'] ?></span></td>
              <td><div class="btn-group btn-group-sm">
                <a href="categories.php?edit=<?= $c['category_id'] ?>" class="btn btn-info"><i class="fas fa-edit"></i></a>
                <button type="button" class="btn btn-danger js-delete" data-id="<?= $c['category_id'] ?>" data-name="<?= e($c['category_name']) ?>"><i class="fas fa-trash"></i></button>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><form method="post" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Delete category</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">Delete category <b id="delName"></b>?</div>
    <input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" id="delId">
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
