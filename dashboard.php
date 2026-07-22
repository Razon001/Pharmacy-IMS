<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = db();

$todaySales   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetchColumn();
$todayCount   = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetchColumn();
$monthSales   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'")->fetchColumn();
$monthProfit  = (float)$pdo->query("SELECT COALESCE(SUM(profit),0) FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE()) AND status='completed'")->fetchColumn();
$totalMeds    = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE status=1")->fetchColumn();
$totalCust    = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$stockValue   = (float)$pdo->query("SELECT COALESCE(SUM(quantity*purchase_price),0) FROM batches")->fetchColumn();

$lowStock  = low_stock_medicines();
$expSoon   = expiring_batches(60);

$recent = $pdo->query(
  "SELECT s.*, c.name AS customer FROM sales s
   LEFT JOIN customers c ON c.id=s.customer_id
   ORDER BY s.id DESC LIMIT 8"
)->fetchAll();

$topMeds = $pdo->query(
  "SELECT m.name, SUM(si.quantity) qty, SUM(si.subtotal) revenue
   FROM sale_items si JOIN medicines m ON m.id=si.medicine_id
   JOIN sales s ON s.id=si.sale_id AND s.status='completed'
   GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 5"
)->fetchAll();

// last 7 days sales for a simple bar chart
$week = $pdo->query(
  "SELECT DATE(sale_date) d, COALESCE(SUM(total),0) t
   FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status='completed'
   GROUP BY DATE(sale_date)"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = (float)($week[$d] ?? 0);
}
$maxDay = max(1, max($days));
?>

<div class="grid grid-4">
  <div class="card stat"><div class="icon bg-green">&#128181;</div>
    <div><div class="n"><?= money($todaySales) ?></div><div class="l">Today's Sales (<?= $todayCount ?> bills)</div></div></div>
  <div class="card stat"><div class="icon bg-blue">&#128200;</div>
    <div><div class="n"><?= money($monthSales) ?></div><div class="l">This Month</div></div></div>
  <div class="card stat"><div class="icon bg-violet">&#128176;</div>
    <div><div class="n"><?= money($monthProfit) ?></div><div class="l">Month Profit</div></div></div>
  <div class="card stat"><div class="icon bg-amber">&#128230;</div>
    <div><div class="n"><?= money($stockValue) ?></div><div class="l">Stock Value (cost)</div></div></div>
</div>

<div class="grid grid-4">
  <div class="card stat"><div class="icon bg-blue">&#128138;</div>
    <div><div class="n"><?= $totalMeds ?></div><div class="l">Medicines</div></div></div>
  <div class="card stat"><div class="icon bg-green">&#128100;</div>
    <div><div class="n"><?= $totalCust ?></div><div class="l">Customers</div></div></div>
  <div class="card stat"><div class="icon bg-red">&#9888;</div>
    <div><div class="n"><?= count($lowStock) ?></div><div class="l">Low / Out of Stock</div></div></div>
  <div class="card stat"><div class="icon bg-amber">&#9203;</div>
    <div><div class="n"><?= count($expSoon) ?></div><div class="l">Expiring &le; 60 days</div></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Last 7 Days Sales</h2></div>
    <div style="display:flex;align-items:flex-end;gap:.6rem;height:180px">
      <?php foreach ($days as $d => $t):
        $h = (int)round(($t / $maxDay) * 150); ?>
        <div style="flex:1;text-align:center">
          <div title="<?= money($t) ?>" style="height:<?= max(4,$h) ?>px;background:var(--primary);border-radius:6px 6px 0 0"></div>
          <small><?= date('D', strtotime($d)) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Top Selling Medicines</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Medicine</th><th class="text-right">Qty Sold</th><th class="text-right">Revenue</th></tr></thead>
      <tbody>
      <?php if (!$topMeds): ?><tr><td colspan="3" class="text-center muted">No sales yet</td></tr><?php endif; ?>
      <?php foreach ($topMeds as $t): ?>
        <tr><td><?= e($t['name']) ?></td><td class="text-right"><?= (int)$t['qty'] ?></td>
        <td class="text-right"><?= money($t['revenue']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Low / Out of Stock</h2><a class="btn btn-sm btn-ghost" href="expiry.php">View all</a></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Medicine</th><th class="text-right">In Stock</th><th class="text-right">Reorder At</th><th></th></tr></thead>
      <tbody>
      <?php if (!$lowStock): ?><tr><td colspan="4" class="text-center muted">All good &#128077;</td></tr><?php endif; ?>
      <?php foreach (array_slice($lowStock,0,6) as $m): ?>
        <tr><td><?= e($m['name']) ?></td>
          <td class="text-right"><?= (int)$m['stock'] ?></td>
          <td class="text-right"><?= (int)$m['reorder_level'] ?></td>
          <td class="text-right"><span class="badge <?= $m['stock']==0?'badge-red':'badge-amber' ?>"><?= $m['stock']==0?'Out':'Low' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Recent Sales</h2><a class="btn btn-sm btn-ghost" href="sales.php">View all</a></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Invoice</th><th>Customer</th><th class="text-right">Total</th><th>Time</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="4" class="text-center muted">No sales yet</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr><td><a href="invoice.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_no']) ?></a></td>
          <td><?= e($r['customer'] ?: 'Walk-in') ?></td>
          <td class="text-right"><?= money($r['total']) ?></td>
          <td><?= date('d M, H:i', strtotime($r['sale_date'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
