<?php
$pageTitle = 'Purchases';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','pharmacist']);
$pdo = db();

require_once __DIR__ . '/includes/header.php';
$from = get('from', date('Y-m-01'));
$to   = get('to', date('Y-m-d'));
$st = $pdo->prepare(
  'SELECT p.*, s.name supplier FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id
   WHERE p.purchase_date BETWEEN ? AND ? ORDER BY p.id DESC'
);
$st->execute([$from,$to]);
$rows = $st->fetchAll();
$sum = array_sum(array_column($rows,'total'));
?>
<div class="card">
  <form class="card-head" method="get">
    <h2>Purchases (Stock In)</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <input type="date" name="from" value="<?= e($from) ?>">
      <input type="date" name="to" value="<?= e($to) ?>">
      <button class="btn btn-sm">Filter</button>
      <a class="btn btn-sm" href="purchase_add.php">+ New Purchase</a>
    </div>
  </form>
  <p class="muted">Total purchased in range: <strong><?= money($sum) ?></strong></p>
  <div class="table-wrap"><table>
    <thead><tr><th>Invoice</th><th>Date</th><th>Supplier</th><th>Status</th>
      <th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">View</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center muted">No purchases in range.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['invoice_no']) ?></td><td><?= date('d M Y',strtotime($r['purchase_date'])) ?></td>
        <td><?= e($r['supplier'] ?: '-') ?></td>
        <td><span class="badge <?= $r['payment_status']==='paid'?'badge-green':($r['payment_status']==='due'?'badge-red':'badge-amber') ?>"><?= e(ucfirst($r['payment_status'])) ?></span></td>
        <td class="text-right"><?= money($r['total']) ?></td><td class="text-right"><?= money($r['paid']) ?></td>
        <td class="text-right"><a class="btn btn-sm btn-ghost" href="purchase_view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
