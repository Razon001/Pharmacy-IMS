<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

$id = (int)get('id');
$st = $pdo->prepare(
  'SELECT s.*, c.name customer, c.phone cust_phone, u.name cashier
   FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
   LEFT JOIN users u ON u.id=s.user_id WHERE s.id=?'
);
$st->execute([$id]);
$sale = $st->fetch();
if (!$sale) { die('Invoice not found.'); }

$items = $pdo->prepare(
  'SELECT si.*, m.name FROM sale_items si JOIN medicines m ON m.id=si.medicine_id
   WHERE si.sale_id=? ORDER BY si.id'
);
$items->execute([$id]);
$rows = $items->fetchAll();

// merge rows that were split across batches for display
$merged = [];
foreach ($rows as $r) {
    $k = $r['medicine_id'] . '_' . $r['price'];
    if (!isset($merged[$k])) $merged[$k] = ['name'=>$r['name'],'price'=>$r['price'],'qty'=>0,'subtotal'=>0];
    $merged[$k]['qty'] += $r['quantity'];
    $merged[$k]['subtotal'] += $r['subtotal'];
}
$set = settings();
$autoprint = get('print') === '1' ? '1' : '0';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Invoice <?= e($sale['invoice_no']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-autoprint="<?= $autoprint ?>" style="background:#f1f5f9">
<div class="page" style="max-width:720px;margin:1.5rem auto">
  <div class="card">
    <div class="no-print" style="display:flex;gap:.6rem;margin-bottom:1rem">
      <a class="btn btn-sm" href="pos.php">&larr; New Sale</a>
      <a class="btn btn-sm btn-ghost" href="sales.php">All Sales</a>
      <button class="btn btn-sm btn-amber" onclick="window.print()">Print</button>
    </div>

    <div style="text-align:center;border-bottom:2px solid var(--line);padding-bottom:1rem;margin-bottom:1rem">
      <h1 style="margin:0"><?= e($set['pharmacy_name']) ?></h1>
      <div class="muted"><?= e($set['address']) ?></div>
      <div class="muted"><?= e($set['phone']) ?> <?= $set['email']?' &middot; '.e($set['email']):'' ?></div>
    </div>

    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
      <div>
        <div><strong>Invoice:</strong> <?= e($sale['invoice_no']) ?></div>
        <div><strong>Date:</strong> <?= date('d M Y, H:i', strtotime($sale['sale_date'])) ?></div>
        <div><strong>Cashier:</strong> <?= e($sale['cashier'] ?: '-') ?></div>
      </div>
      <div style="text-align:right">
        <div><strong>Customer:</strong> <?= e($sale['customer'] ?: 'Walk-in') ?></div>
        <?php if ($sale['cust_phone']): ?><div><?= e($sale['cust_phone']) ?></div><?php endif; ?>
        <?php if ($sale['doctor_name']): ?><div><strong>Dr.</strong> <?= e($sale['doctor_name']) ?></div><?php endif; ?>
        <?php if ($sale['status']==='returned'): ?><div><span class="badge badge-red">RETURNED</span></div><?php endif; ?>
      </div>
    </div>

    <table>
      <thead><tr><th>#</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>
      <tbody>
      <?php $i=1; foreach ($merged as $m): ?>
        <tr><td><?= $i++ ?></td><td><?= e($m['name']) ?></td>
          <td class="text-right"><?= (int)$m['qty'] ?></td>
          <td class="text-right"><?= money($m['price']) ?></td>
          <td class="text-right"><?= money($m['subtotal']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div style="margin-left:auto;max-width:280px;margin-top:1rem">
      <div style="display:flex;justify-content:space-between"><span class="muted">Sub Total</span><span><?= money($sale['sub_total']) ?></span></div>
      <?php if ($sale['discount']>0): ?><div style="display:flex;justify-content:space-between"><span class="muted">Discount</span><span>- <?= money($sale['discount']) ?></span></div><?php endif; ?>
      <div style="display:flex;justify-content:space-between"><span class="muted">Tax</span><span><?= money($sale['tax']) ?></span></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);margin-top:.4rem;padding-top:.4rem;font-size:1.15rem;font-weight:700">
        <span>Total</span><span><?= money($sale['total']) ?></span></div>
      <div style="display:flex;justify-content:space-between"><span class="muted">Paid (<?= e(strtoupper($sale['payment_method'])) ?>)</span><span><?= money($sale['paid']) ?></span></div>
      <div style="display:flex;justify-content:space-between"><span class="muted">Change</span><span><?= money($sale['change_amount']) ?></span></div>
    </div>

    <p style="text-align:center;margin-top:1.5rem" class="muted"><?= e($set['footer_note']) ?></p>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body></html>
