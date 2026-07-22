<?php
require_once __DIR__ . '/functions.php';
require_login();
$u = current_user();
$set = settings();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> &middot; <?= e($set['pharmacy_name'] ?? 'Pharmacy') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <span class="logo">&#9877;</span>
      <div>
        <strong><?= e($set['pharmacy_name'] ?? 'Pharmacy') ?></strong>
        <small><?= e(ucfirst($u['role'])) ?></small>
      </div>
    </div>
    <nav>
      <a class="<?= nav_active('dashboard.php') ?>" href="dashboard.php"><i>&#9632;</i> Dashboard</a>
      <a class="<?= nav_active('pos.php') ?>" href="pos.php"><i>&#128179;</i> New Sale (POS)</a>
      <a class="<?= nav_active('sales.php') ?>" href="sales.php"><i>&#128181;</i> Sales</a>
      <a class="<?= nav_active('medicines.php') ?>" href="medicines.php"><i>&#128138;</i> Medicines</a>
      <a class="<?= nav_active('categories.php') ?>" href="categories.php"><i>&#128193;</i> Categories</a>
      <?php if (has_role(['admin','pharmacist'])): ?>
      <a class="<?= nav_active('purchases.php') ?>" href="purchases.php"><i>&#128230;</i> Purchases</a>
      <a class="<?= nav_active('suppliers.php') ?>" href="suppliers.php"><i>&#127981;</i> Suppliers</a>
      <?php endif; ?>
      <a class="<?= nav_active('customers.php') ?>" href="customers.php"><i>&#128100;</i> Customers</a>
      <a class="<?= nav_active('prescriptions.php') ?>" href="prescriptions.php"><i>&#128203;</i> Prescriptions</a>
      <a class="<?= nav_active('expiry.php') ?>" href="expiry.php"><i>&#9888;</i> Expiry &amp; Stock</a>
      <?php if (has_role(['admin','pharmacist'])): ?>
      <a class="<?= nav_active('reports.php') ?>" href="reports.php"><i>&#128202;</i> Reports</a>
      <?php endif; ?>
      <?php if (has_role(['admin'])): ?>
      <a class="<?= nav_active('users.php') ?>" href="users.php"><i>&#128101;</i> Users</a>
      <a class="<?= nav_active('settings.php') ?>" href="settings.php"><i>&#9881;</i> Settings</a>
      <?php endif; ?>
    </nav>
  </aside>

  <main class="content">
    <header class="topbar">
      <h1><?= e($pageTitle) ?></h1>
      <div class="user-menu">
        <span><?= e($u['name']) ?></span>
        <a class="btn btn-sm btn-ghost" href="logout.php">Logout</a>
      </div>
    </header>

    <div class="page">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
