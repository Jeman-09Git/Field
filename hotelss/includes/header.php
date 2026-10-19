<?php $page = basename($_SERVER['PHP_SELF']); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hotel Admin | <?= e($title ?? '') ?></title>
  <!-- AdminLTE 3 + dependencies from CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
      <li class="nav-item d-none d-sm-inline-block"><a href="employees.php" class="nav-link">Employees</a></li>
      <li class="nav-item d-none d-sm-inline-block"><a href="rooms.php" class="nav-link">Rooms</a></li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="employees.php" class="brand-link">
      <i class="fas fa-hotel brand-image mt-1 ml-3" style="opacity:.8"></i>
      <span class="brand-text font-weight-light">Hotel Admin</span>
    </a>
    <div class="sidebar">
      <!-- COMPONENT: User Panel -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User">
        </div>
        <div class="info"><a href="#" class="d-block">Jeman (Admin)</a></div>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
          <li class="nav-item">
            <a href="employees.php" class="nav-link <?= $page === 'employees.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-users"></i><p>Employees</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="rooms.php" class="nav-link <?= $page === 'rooms.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-bed"></i><p>Rooms</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid"><h1><?= e($title ?? '') ?></h1></div>
    </section>
    <section class="content">
      <div class="container-fluid">
