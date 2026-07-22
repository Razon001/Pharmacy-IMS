<?php
$pageTitle = 'Purchase Detail';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','pharmacist']);
$pdo = db();

$id = (int)get('id');
$st = $pdo->prepare('SELECT p.*, s.name supplier, s.phone, u.name added_by FROM purchases p
   LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN users u ON u.id=p.user_id WHERE p.id=?');
$st->execute([$id]);
$pur = $st->fetch();
if (!$pur) { die('Purchase not found.'); }
$items = $pdo->prepare('SELECT pi.*, m.name FROM purchase_items pi JOIN medicines m ON m.id=pi.medicine_id WHERE pi.purchase_id=?');
$items->execute([$id]);
$items = $items->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-head">
    <h2>Purchase <?= e($pur['invoice_no']) ?></h2>
    <a class="btn btn-sm btn-ghost" href="purchases.php">Back</a>
  </div>
  <div class="grid grid-3 mb">
    <div><small class="muted">Supplier</small><div><?= e($pur['supplier'] ?: '-') ?></div></div>
    <div><small class="muted">Date</small><div><?= date('d M Y',strtotime($pur['purchase_date'])) ?></div></div>
    <div><small class="muted">Added by</small><div><?= e($pur['added_by'] ?: '-') ?></div></div>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-right">Qty</th>
      <th class="text-right">Cost</th><th class="text-right">Sale</th><th class="text-right">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['name']) ?></td><td><?= e($it['batch_no'] ?: '-') ?></td>
        <td><?= $it['expiry_date']?date('d M Y',strtotime($it['expiry_date'])):'-' ?></td>
        <td class="text-right"><?= (int)$it['quantity'] ?></td>
        <td class="text-right"><?= money($it['purchase_price']) ?></td>
        <td class="text-right"><?= money($it['sale_price']) ?></td>
        <td class="text-right"><?= money($it['subtotal']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div style="margin-left:auto;max-width:280px;margin-top:1rem">
    <div style="display:flex;justify-content:space-between"><span class="muted">Sub Total</span><span><?= money($pur['sub_total']) ?></span></div>
    <div style="display:flex;justify-content:space-between"><span class="muted">Discount</span><span>- <?= money($pur['discount']) ?></span></div>
    <div style="display:flex;justify-content:space-between"><span class="muted">Tax</span><span><?= money($pur['tax']) ?></span></div>
    <div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--line);margin-top:.4rem;padding-top:.4rem">
      <span>Total</span><span><?= money($pur['total']) ?></span></div>
    <div style="display:flex;justify-content:space-between"><span class="muted">Paid</span><span><?= money($pur['paid']) ?></span></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
