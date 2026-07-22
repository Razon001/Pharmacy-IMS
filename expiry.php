<?php
$pageTitle = 'Expiry & Stock';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

$days = (int)get('days', 60);
if ($days <= 0) {
    $days = 60;
}

$expiring = expiring_batches($days);
$low = low_stock_medicines();

// Split low-stock into out-of-stock vs running-low.
$out = array_values(array_filter($low, fn($r) => (int)$r['stock'] <= 0));
$running = array_values(array_filter($low, fn($r) => (int)$r['stock'] > 0));

$today = new DateTimeImmutable('today');
require_once __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4">
  <div class="card stat"><div class="icon bg-amber">&#9203;</div>
    <div><div class="n"><?= count($expiring) ?></div><div class="l">Expiring &le; <?= $days ?>d</div></div></div>
  <div class="card stat"><div class="icon bg-red">&#9888;</div>
    <div><div class="n"><?= count($out) ?></div><div class="l">Out of Stock</div></div></div>
  <div class="card stat"><div class="icon bg-blue">&#128138;</div>
    <div><div class="n"><?= count($running) ?></div><div class="l">Running Low</div></div></div>
  <div class="card stat"><div class="icon bg-green">&#9989;</div>
    <div><div class="n" style="font-size:1rem"><a class="btn btn-sm" href="batches.php">Adjust Stock</a></div><div class="l">Batches</div></div></div>
</div>

<div class="card">
  <form class="card-head" method="get">
    <h2>Expiring Batches</h2>
    <div style="display:flex;gap:.5rem;align-items:center">
      <label style="margin:0">Window:</label>
      <select name="days" onchange="this.form.submit()">
        <?php foreach ([30, 60, 90, 180, 365] as $d): ?>
          <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>><?= $d ?> days</option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm">Go</button></noscript>
    </div>
  </form>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Medicine</th><th>Batch #</th><th>Expiry</th><th>Days left</th><th class="text-right">Qty</th><th>Status</th></tr>
      </thead>
      <tbody>
      <?php if (!$expiring): ?>
        <tr><td colspan="6" class="muted">Nothing expiring within <?= $days ?> days. </td></tr>
      <?php endif; ?>
      <?php foreach ($expiring as $b): ?>
        <?php
          $exp = new DateTimeImmutable($b['expiry_date']);
          $left = (int)$today->diff($exp)->format('%r%a');
          if ($left < 0)      { $cls = 'badge-red';   $txt = 'Expired'; }
          elseif ($left <= 30){ $cls = 'badge-amber'; $txt = 'Critical'; }
          else                { $cls = 'badge-blue';  $txt = 'Soon'; }
        ?>
        <tr>
          <td><strong><?= e($b['medicine_name']) ?></strong></td>
          <td><?= e($b['batch_no'] ?? '&mdash;') ?></td>
          <td><?= e(date('d M Y', strtotime($b['expiry_date']))) ?></td>
          <td><?= $left ?></td>
          <td class="text-right"><?= (int)$b['quantity'] ?></td>
          <td><span class="badge <?= $cls ?>"><?= $txt ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Out of Stock</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Medicine</th><th class="text-right">Reorder level</th><th class="text-right">Action</th></tr></thead>
        <tbody>
        <?php if (!$out): ?><tr><td colspan="3" class="muted">None.</td></tr><?php endif; ?>
        <?php foreach ($out as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td class="text-right"><?= (int)$r['reorder_level'] ?></td>
            <td class="text-right"><a class="btn btn-sm" href="batches.php?id=<?= (int)$r['id'] ?>">Add Stock</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Running Low</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Medicine</th><th class="text-right">Stock</th><th class="text-right">Reorder</th><th class="text-right">Action</th></tr></thead>
        <tbody>
        <?php if (!$running): ?><tr><td colspan="4" class="muted">None.</td></tr><?php endif; ?>
        <?php foreach ($running as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td class="text-right"><span class="badge badge-amber"><?= (int)$r['stock'] ?></span></td>
            <td class="text-right"><?= (int)$r['reorder_level'] ?></td>
            <td class="text-right"><a class="btn btn-sm" href="batches.php?id=<?= (int)$r['id'] ?>">Add Stock</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
