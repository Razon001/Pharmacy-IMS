<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'pharmacist']);
$pdo = db();

/* ---------- Date range (defaults to current month) ---------- */
$from = trim(get('from')) ?: date('Y-m-01');
$to   = trim(get('to'))   ?: date('Y-m-d');
// Basic validation / normalisation.
$fromD = date('Y-m-d', strtotime($from) ?: time());
$toD   = date('Y-m-d', strtotime($to) ?: time());
if ($fromD > $toD) {
    [$fromD, $toD] = [$toD, $fromD];
}
$start = $fromD . ' 00:00:00';
$end   = $toD . ' 23:59:59';

/* ---------- Core sales aggregates ---------- */
$sumStmt = $pdo->prepare(
    "SELECT COUNT(*) bills,
            COALESCE(SUM(total),0) revenue,
            COALESCE(SUM(profit),0) profit,
            COALESCE(SUM(discount),0) discount,
            COALESCE(SUM(tax),0) tax
     FROM sales WHERE status='completed' AND sale_date BETWEEN ? AND ?"
);
$sumStmt->execute([$start, $end]);
$sum = $sumStmt->fetch();

$itemsSold = (int)(function () use ($pdo, $start, $end) {
    $s = $pdo->prepare(
        "SELECT COALESCE(SUM(si.quantity),0)
         FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ?"
    );
    $s->execute([$start, $end]);
    return $s->fetchColumn();
})();

$returns = $pdo->prepare(
    "SELECT COUNT(*) n, COALESCE(SUM(amount),0) amt
     FROM sale_returns WHERE created_at BETWEEN ? AND ?"
);
$returns->execute([$start, $end]);
$ret = $returns->fetch();

$avgBill = $sum['bills'] ? $sum['revenue'] / $sum['bills'] : 0;

/* ---------- Payment method breakdown ---------- */
$payStmt = $pdo->prepare(
    "SELECT payment_method, COUNT(*) bills, COALESCE(SUM(total),0) amt
     FROM sales WHERE status='completed' AND sale_date BETWEEN ? AND ?
     GROUP BY payment_method ORDER BY amt DESC"
);
$payStmt->execute([$start, $end]);
$payRows = $payStmt->fetchAll();

/* ---------- Daily sales series ---------- */
$dayStmt = $pdo->prepare(
    "SELECT DATE(sale_date) d, COALESCE(SUM(total),0) t
     FROM sales WHERE status='completed' AND sale_date BETWEEN ? AND ?
     GROUP BY DATE(sale_date) ORDER BY d"
);
$dayStmt->execute([$start, $end]);
$daily = $dayStmt->fetchAll();
$maxDay = 0;
foreach ($daily as $d) {
    $maxDay = max($maxDay, (float)$d['t']);
}
$maxDay = $maxDay ?: 1;

/* ---------- Top medicines ---------- */
$topStmt = $pdo->prepare(
    "SELECT m.name, SUM(si.quantity) qty,
            SUM(si.subtotal) revenue,
            SUM((si.price - si.cost) * si.quantity) profit
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id AND s.status='completed'
     JOIN medicines m ON m.id=si.medicine_id
     WHERE s.sale_date BETWEEN ? AND ?
     GROUP BY si.medicine_id ORDER BY qty DESC LIMIT 15"
);
$topStmt->execute([$start, $end]);
$topMeds = $topStmt->fetchAll();

/* ---------- Category-wise sales ---------- */
$catStmt = $pdo->prepare(
    "SELECT COALESCE(c.name,'Uncategorised') category,
            SUM(si.quantity) qty, SUM(si.subtotal) revenue
     FROM sale_items si
     JOIN sales s ON s.id=si.sale_id AND s.status='completed'
     JOIN medicines m ON m.id=si.medicine_id
     LEFT JOIN categories c ON c.id=m.category_id
     WHERE s.sale_date BETWEEN ? AND ?
     GROUP BY category ORDER BY revenue DESC"
);
$catStmt->execute([$start, $end]);
$catRows = $catStmt->fetchAll();

/* ---------- Purchases in range ---------- */
$purStmt = $pdo->prepare(
    "SELECT COUNT(*) n, COALESCE(SUM(total),0) amt
     FROM purchases WHERE purchase_date BETWEEN ? AND ?"
);
$purStmt->execute([$start, $end]);
$pur = $purStmt->fetch();

/* ---------- Current inventory valuation (snapshot, not range) ---------- */
$inv = $pdo->query(
    "SELECT COALESCE(SUM(quantity),0) units,
            COALESCE(SUM(quantity*purchase_price),0) cost_val,
            COALESCE(SUM(quantity*sale_price),0) retail_val
     FROM batches"
)->fetch();
$invMargin = $inv['retail_val'] - $inv['cost_val'];

/* ---------- CSV export of top medicines ---------- */
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $fromD . '_to_' . $toD . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Pharmacy Report', settings()['pharmacy_name'] ?? '']);
    fputcsv($out, ['Period', $fromD . ' to ' . $toD]);
    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Bills', $sum['bills']]);
    fputcsv($out, ['Revenue', $sum['revenue']]);
    fputcsv($out, ['Profit', $sum['profit']]);
    fputcsv($out, ['Discount given', $sum['discount']]);
    fputcsv($out, ['Tax collected', $sum['tax']]);
    fputcsv($out, ['Items sold', $itemsSold]);
    fputcsv($out, ['Returns', $ret['n'], $ret['amt']]);
    fputcsv($out, []);
    fputcsv($out, ['Top Medicines']);
    fputcsv($out, ['Medicine', 'Qty', 'Revenue', 'Profit']);
    foreach ($topMeds as $t) {
        fputcsv($out, [$t['name'], $t['qty'], $t['revenue'], $t['profit']]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/includes/header.php';
$qs = 'from=' . urlencode($fromD) . '&to=' . urlencode($toD);
?>
<div class="card">
  <form class="card-head" method="get" style="flex-wrap:wrap;gap:.75rem">
    <h2>Report Period</h2>
    <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
      <div><label style="margin:0">From</label><input type="date" name="from" value="<?= e($fromD) ?>"></div>
      <div><label style="margin:0">To</label><input type="date" name="to" value="<?= e($toD) ?>"></div>
      <button class="btn">Apply</button>
      <a class="btn btn-ghost" href="reports.php?<?= $qs ?>&export=csv">Export CSV</a>
      <a class="btn btn-ghost" href="javascript:window.print()">Print</a>
    </div>
  </form>
  <p class="muted">Showing <strong><?= e(date('d M Y', strtotime($fromD))) ?></strong> to <strong><?= e(date('d M Y', strtotime($toD))) ?></strong>.</p>
</div>

<div class="grid grid-4">
  <div class="card stat"><div class="icon bg-green">&#128181;</div>
    <div><div class="n"><?= money($sum['revenue']) ?></div><div class="l">Revenue (<?= (int)$sum['bills'] ?> bills)</div></div></div>
  <div class="card stat"><div class="icon bg-violet">&#128176;</div>
    <div><div class="n"><?= money($sum['profit']) ?></div><div class="l">Gross Profit</div></div></div>
  <div class="card stat"><div class="icon bg-blue">&#128138;</div>
    <div><div class="n"><?= (int)$itemsSold ?></div><div class="l">Items Sold</div></div></div>
  <div class="card stat"><div class="icon bg-amber">&#129534;</div>
    <div><div class="n"><?= money($avgBill) ?></div><div class="l">Avg. Bill Value</div></div></div>
</div>

<div class="grid grid-4">
  <div class="card stat"><div class="icon bg-blue">&#127991;</div>
    <div><div class="n"><?= money($sum['discount']) ?></div><div class="l">Discounts Given</div></div></div>
  <div class="card stat"><div class="icon bg-green">&#129534;</div>
    <div><div class="n"><?= money($sum['tax']) ?></div><div class="l">Tax Collected</div></div></div>
  <div class="card stat"><div class="icon bg-red">&#8630;</div>
    <div><div class="n"><?= money($ret['amt']) ?></div><div class="l">Returns (<?= (int)$ret['n'] ?>)</div></div></div>
  <div class="card stat"><div class="icon bg-amber">&#128230;</div>
    <div><div class="n"><?= money($pur['amt']) ?></div><div class="l">Purchases (<?= (int)$pur['n'] ?>)</div></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Daily Sales</h2></div>
    <?php if (!$daily): ?>
      <p class="muted">No sales in this period.</p>
    <?php else: ?>
      <div style="display:flex;align-items:flex-end;gap:.4rem;height:180px;overflow-x:auto">
        <?php foreach ($daily as $d):
          $h = (int)round(((float)$d['t'] / $maxDay) * 150); ?>
          <div style="min-width:26px;flex:1;text-align:center">
            <div title="<?= e(date('d M', strtotime($d['d']))) . ': ' . money($d['t']) ?>"
                 style="height:<?= max(4, $h) ?>px;background:var(--primary);border-radius:6px 6px 0 0"></div>
            <small><?= date('j', strtotime($d['d'])) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Payment Methods</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Method</th><th class="text-right">Bills</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
        <?php if (!$payRows): ?><tr><td colspan="3" class="muted">No data.</td></tr><?php endif; ?>
        <?php foreach ($payRows as $p): ?>
          <tr>
            <td><?= e(ucfirst($p['payment_method'])) ?></td>
            <td class="text-right"><?= (int)$p['bills'] ?></td>
            <td class="text-right"><?= money($p['amt']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Top Medicines</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Medicine</th><th class="text-right">Qty</th><th class="text-right">Revenue</th><th class="text-right">Profit</th></tr></thead>
        <tbody>
        <?php if (!$topMeds): ?><tr><td colspan="4" class="muted">No sales in this period.</td></tr><?php endif; ?>
        <?php foreach ($topMeds as $t): ?>
          <tr>
            <td><?= e($t['name']) ?></td>
            <td class="text-right"><?= (int)$t['qty'] ?></td>
            <td class="text-right"><?= money($t['revenue']) ?></td>
            <td class="text-right"><?= money($t['profit']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Sales by Category</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Category</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr></thead>
        <tbody>
        <?php if (!$catRows): ?><tr><td colspan="3" class="muted">No data.</td></tr><?php endif; ?>
        <?php foreach ($catRows as $c): ?>
          <tr>
            <td><?= e($c['category']) ?></td>
            <td class="text-right"><?= (int)$c['qty'] ?></td>
            <td class="text-right"><?= money($c['revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Current Inventory Valuation</h2><span class="muted">Live snapshot</span></div>
  <div class="grid grid-4">
    <div class="stat-box"><div class="l">Units in stock</div><div class="n"><?= (int)$inv['units'] ?></div></div>
    <div class="stat-box"><div class="l">Cost value</div><div class="n"><?= money($inv['cost_val']) ?></div></div>
    <div class="stat-box"><div class="l">Retail value</div><div class="n"><?= money($inv['retail_val']) ?></div></div>
    <div class="stat-box"><div class="l">Potential margin</div><div class="n"><?= money($invMargin) ?></div></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
