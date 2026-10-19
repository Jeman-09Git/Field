<?php
/*
 * Shared layout (top part). Each page sets before including this file:
 *   $pageTitle  - heading shown on the page
 *   $active     - sidebar item to highlight (dashboard, cars, categories, customers, rentals, payments, maintenance)
 *   $crumbs     - breadcrumb list: [['Home','index.php'], ['Cars', null]]
 */
require_once __DIR__ . '/../config.php';

$pageTitle = $pageTitle ?? 'Car Rental';
$active    = $active ?? '';
$crumbs    = $crumbs ?? [['Home', 'index.php']];

$menu = [
    'dashboard'   => ['index.php',       'fas fa-tachometer-alt', 'Dashboard'],
    'cars'        => ['cars.php',        'fas fa-car',            'Cars'],
    'categories'  => ['categories.php',  'fas fa-tags',           'Categories'],
    'customers'   => ['customers.php',   'fas fa-users',          'Customers'],
    'rentals'     => ['rentals.php',     'fas fa-key',            'Rentals'],
    'payments'    => ['payments.php',    'fas fa-money-bill-wave', 'Payments'],
    'maintenance' => ['maintenance.php', 'fas fa-tools',          'Maintenance'],
];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Car Rental System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Top navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
      <li class="nav-item d-none d-sm-inline-block"><a href="index.php" class="nav-link">Home</a></li>
      <li class="nav-item d-none d-sm-inline-block"><a href="rental_form.php" class="nav-link">New rental</a></li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item"><span class="nav-link text-muted"><i class="far fa-calendar-alt"></i> <?= date('D, d M Y') ?></span></li>
    </ul>
  </nav>

  <!-- Sidebar navigation menu -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link">
      <i class="fas fa-car-side brand-image img-circle elevation-3" style="opacity:.9;line-height:33px;text-align:center;width:33px;background:#007bff"></i>
      <span class="brand-text font-weight-light">Car Rental System</span>
    </a>
    <div class="sidebar">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          <?php foreach ($menu as $key => [$url, $icon, $label]): ?>
            <li class="nav-item">
              <a href="<?= $url ?>" class="nav-link <?= $active === $key ? 'active' : '' ?>">
                <i class="nav-icon <?= $icon ?>"></i>
                <p><?= $label ?></p>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </aside>

  <!-- Page content -->
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1><?= e($pageTitle) ?></h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <?php foreach ($crumbs as $i => [$label, $url]): ?>
                <?php if ($url && $i < count($crumbs) - 1): ?>
                  <li class="breadcrumb-item"><a href="<?= $url ?>"><?= e($label) ?></a></li>
                <?php else: ?>
                  <li class="breadcrumb-item active"><?= e($label) ?></li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash[0]) ?> alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h5><i class="icon fas <?= $flash[0] === 'success' ? 'fa-check' : 'fa-ban' ?>"></i>
              <?= $flash[0] === 'success' ? 'Done' : 'Problem' ?></h5>
            <?= e($flash[1]) ?>
          </div>
        <?php endif; ?>
