<?php
require 'includes/db.php';
$title = 'Employees';
$msg = '';

// ---- Add employee (form uses a Select for the age group helper + spinner on submit) ---- //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['Name'] ?? '');
    $age     = (int)($_POST['Age'] ?? 0);
    $contact = trim($_POST['Contact'] ?? '');
    $pass    = $_POST['Password'] ?? '';

    if ($name === '' || $age < 16 || $contact === '' || strlen($pass) < 4) {
        $msg = '<div class="alert alert-danger">Please fill all fields (age 16+, password 4+ characters).</div>';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO employees (Name, Age, Contact, Password) VALUES (?,?,?,?)');
        $stmt->bind_param('siss', $name, $age, $contact, $hash);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Employee added successfully.</div>';
    }
}

// ---- Filter by Select dropdown ---- //
$filter = $_GET['age_group'] ?? 'all';
$sql = 'SELECT EmployeeID, Name, Age, Contact FROM employees';
switch ($filter) {
    case 'young':  $sql .= ' WHERE Age < 25'; break;
    case 'middle': $sql .= ' WHERE Age BETWEEN 25 AND 40'; break;
    case 'senior': $sql .= ' WHERE Age > 40'; break;
    default: $filter = 'all';
}
$sql .= ' ORDER BY EmployeeID DESC';
$employees = $conn->query($sql);

require 'includes/header.php';
?>

<?= $msg ?>

<div class="row">
  <!-- Add employee form -->
  <div class="col-md-4">
    <div class="card card-primary">
      <div class="card-header"><h3 class="card-title">Add Employee</h3></div>
      <form method="post">
        <div class="card-body">
          <div class="form-group"><label>Name</label>
            <input type="text" name="Name" class="form-control" required></div>
          <div class="form-group"><label>Age</label>
            <input type="number" name="Age" class="form-control" min="16" required></div>
          <div class="form-group"><label>Contact</label>
            <input type="text" name="Contact" class="form-control" required></div>
          <div class="form-group"><label>Password</label>
            <input type="password" name="Password" class="form-control" required></div>
        </div>
        <div class="card-footer"><button class="btn btn-primary">Save Employee</button></div>
      </form>
    </div>
  </div>

  <!-- Employee list with Select filter + Spinner overlay -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Employee List</h3>
        <div class="card-tools" style="width:220px">
          <!-- COMPONENT: Select -->
          <form method="get">
            <select name="age_group" class="form-control select2 auto-submit">
              <option value="all"    <?= $filter==='all'?'selected':'' ?>>All ages</option>
              <option value="young"  <?= $filter==='young'?'selected':'' ?>>Under 25</option>
              <option value="middle" <?= $filter==='middle'?'selected':'' ?>>25 - 40</option>
              <option value="senior" <?= $filter==='senior'?'selected':'' ?>>Over 40</option>
            </select>
          </form>
        </div>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
          <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Contact</th></tr></thead>
          <tbody>
          <?php if ($employees->num_rows === 0): ?>
            <tr><td colspan="4" class="text-center text-muted">No employees found.</td></tr>
          <?php endif; ?>
          <?php while ($r = $employees->fetch_assoc()): ?>
            <tr>
              <td><?= e($r['EmployeeID']) ?></td>
              <td><?= e($r['Name']) ?></td>
              <td><?= e($r['Age']) ?></td>
              <td><?= e($r['Contact']) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <!-- COMPONENT: Spinner / Loading overlay (shown while filtering/saving) -->
      <div class="overlay dark loading-overlay">
        <i class="fas fa-3x fa-sync-alt fa-spin"></i>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
