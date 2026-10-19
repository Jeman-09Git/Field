<?php
require 'includes/db.php';
$title = 'Rooms';
$msg = '';

$types    = ['Single', 'Double', 'Suite', 'Deluxe'];
$statuses = ['Available', 'Occupied', 'Maintenance'];

// ---- Add room ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = trim($_POST['RoomNumber'] ?? '');
    $type   = $_POST['RoomType'] ?? '';
    $price  = (float)($_POST['Price'] ?? 0);
    $status = $_POST['Status'] ?? 'Available';
    $emp    = (int)($_POST['EmployeeID'] ?? 0) ?: null;

    if ($number === '' || !in_array($type, $types) || !in_array($status, $statuses) || $price <= 0) {
        $msg = '<div class="alert alert-danger">Please fill the form correctly.</div>';
    } else {
        try {
            $stmt = $conn->prepare('INSERT INTO rooms (RoomNumber, RoomType, Price, Status, EmployeeID) VALUES (?,?,?,?,?)');
            $stmt->bind_param('ssdsi', $number, $type, $price, $status, $emp);
            $stmt->execute();
            $msg = '<div class="alert alert-success">Room added successfully.</div>';
        } catch (mysqli_sql_exception $ex) {
            $msg = '<div class="alert alert-danger">Could not save: room number may already exist.</div>';
        }
    }
}

// ---- Filter by status (Select) ----
$filter = $_GET['status'] ?? 'all';
if (in_array($filter, $statuses)) {
    $stmt = $conn->prepare('SELECT r.*, e.Name AS EmpName FROM rooms r LEFT JOIN employees e ON e.EmployeeID=r.EmployeeID WHERE r.Status=? ORDER BY r.RoomNumber');
    $stmt->bind_param('s', $filter);
    $stmt->execute();
    $rooms = $stmt->get_result();
} else {
    $filter = 'all';
    $rooms = $conn->query('SELECT r.*, e.Name AS EmpName FROM rooms r LEFT JOIN employees e ON e.EmployeeID=r.EmployeeID ORDER BY r.RoomNumber');
}

$employees = $conn->query('SELECT EmployeeID, Name FROM employees ORDER BY Name');
$badge = ['Available' => 'success', 'Occupied' => 'danger', 'Maintenance' => 'warning'];

require 'includes/header.php';
?>

<?= $msg ?>

<div class="row">
  <div class="col-md-4">
    <div class="card card-success">
      <div class="card-header"><h3 class="card-title">Add Room</h3></div>
      <form method="post">
        <div class="card-body">
          <div class="form-group"><label>Room Number</label>
            <input type="text" name="RoomNumber" class="form-control" required></div>

          <!-- COMPONENT: Select -->
          <div class="form-group"><label>Room Type</label>
            <select name="RoomType" class="form-control select2" required>
              <option value="">-- choose type --</option>
              <?php foreach ($types as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
            </select></div>

          <div class="form-group"><label>Price (TZS)</label>
            <input type="number" name="Price" class="form-control" min="1" step="0.01" required></div>

          <div class="form-group"><label>Status</label>
            <select name="Status" class="form-control select2">
              <?php foreach ($statuses as $s): ?><option><?= e($s) ?></option><?php endforeach; ?>
            </select></div>

          <div class="form-group"><label>Responsible Employee</label>
            <select name="EmployeeID" class="form-control select2">
              <option value="">-- none --</option>
              <?php while ($e = $employees->fetch_assoc()): ?>
                <option value="<?= e($e['EmployeeID']) ?>"><?= e($e['Name']) ?></option>
              <?php endwhile; ?>
            </select></div>
        </div>
        <div class="card-footer">
          <button class="btn btn-success">Save Room</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Room List</h3>
        <div class="card-tools" style="width:220px">
          <form method="get">
            <select name="status" class="form-control select2 auto-submit">
              <option value="all" <?= $filter==='all'?'selected':'' ?>>All statuses</option>
              <?php foreach ($statuses as $s): ?>
                <option <?= $filter===$s?'selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
          <thead><tr><th>Room</th><th>Type</th><th>Price</th><th>Status</th><th>Employee</th></tr></thead>
          <tbody>
          <?php if ($rooms->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center text-muted">No rooms found.</td></tr>
          <?php endif; ?>
          <?php while ($r = $rooms->fetch_assoc()): ?>
            <tr>
              <td><?= e($r['RoomNumber']) ?></td>
              <td><?= e($r['RoomType']) ?></td>
              <td><?= number_format($r['Price']) ?></td>
              <td><span class="badge badge-<?= $badge[$r['Status']] ?>"><?= e($r['Status']) ?></span></td>
              <td><?= e($r['EmpName'] ?? '-') ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <!-- COMPONENT: Spinner / Loading overlay -->
      <div class="overlay dark loading-overlay">
        <i class="fas fa-3x fa-sync-alt fa-spin"></i>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
