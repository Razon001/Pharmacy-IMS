<?php
$pageTitle = 'New Purchase';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','pharmacist']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $supplierId = (int)post('supplier_id') ?: null;
    $invoiceNo  = trim(post('invoice_no')) ?: ('PUR-' . date('ymdHis'));
    $date       = post('purchase_date') ?: date('Y-m-d');
    $discount   = max(0,(float)post('discount'));
    $tax        = max(0,(float)post('tax'));
    $paid       = max(0,(float)post('paid'));
    $note       = trim(post('note')) ?: null;

    $meds = (array)post('medicine_id', []);
    $items = [];
    $subTotal = 0;
    foreach ($meds as $i => $mid) {
        $mid = (int)$mid;
        $qty = (int)($_POST['quantity'][$i] ?? 0);
        if (!$mid || $qty <= 0) continue;
        $pp = (float)($_POST['purchase_price'][$i] ?? 0);
        $sp = (float)($_POST['sale_price'][$i] ?? 0);
        $bn = trim($_POST['batch_no'][$i] ?? '') ?: null;
        $exp= ($_POST['expiry_date'][$i] ?? '') ?: null;
        $line = $pp * $qty;
        $subTotal += $line;
        $items[] = compact('mid','qty','pp','sp','bn','exp','line');
    }

    if (!$items) {
        flash('Add at least one item with quantity.', 'danger');
        redirect('purchase_add.php');
    }

    $total = $subTotal - $discount + $tax;
    $status = $paid >= $total ? 'paid' : ($paid <= 0 ? 'due' : 'partial');

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
          'INSERT INTO purchases (invoice_no,supplier_id,user_id,sub_total,discount,tax,total,paid,payment_status,note,purchase_date)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([$invoiceNo,$supplierId,current_user()['id'],$subTotal,$discount,$tax,$total,$paid,$status,$note,$date]);
        $pid = (int)$pdo->lastInsertId();

        foreach ($items as $it) {
            $pdo->prepare('INSERT INTO batches (medicine_id,batch_no,supplier_id,expiry_date,quantity,purchase_price,sale_price) VALUES (?,?,?,?,?,?,?)')
                ->execute([$it['mid'],$it['bn'],$supplierId,$it['exp'],$it['qty'],$it['pp'],$it['sp']]);
            $bid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO purchase_items (purchase_id,medicine_id,batch_id,batch_no,expiry_date,quantity,purchase_price,sale_price,subtotal) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$pid,$it['mid'],$bid,$it['bn'],$it['exp'],$it['qty'],$it['pp'],$it['sp'],$it['line']]);
            $pdo->prepare('INSERT INTO stock_movements (medicine_id,batch_id,type,quantity,reference,note,user_id) VALUES (?,?,?,?,?,?,?)')
                ->execute([$it['mid'],$bid,'in',$it['qty'],$invoiceNo,'Purchase',current_user()['id']]);
        }
        $pdo->commit();
        flash('Purchase saved: ' . $invoiceNo, 'success');
        redirect('purchase_view.php?id=' . $pid);
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('Purchase failed: ' . $ex->getMessage(), 'danger');
        redirect('purchase_add.php');
    }
}

require_once __DIR__ . '/includes/header.php';
$suppliers = $pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$medicines = $pdo->query('SELECT id,name,unit FROM medicines WHERE status=1 ORDER BY name')->fetchAll();
$medOptions = '';
foreach ($medicines as $m) { $medOptions .= '<option value="'.$m['id'].'">'.e($m['name']).'</option>'; }
?>
<div class="card">
  <div class="card-head"><h2>New Purchase</h2><a class="btn btn-sm btn-ghost" href="purchases.php">Back</a></div>
  <form method="post" id="purchaseForm">
    <?= csrf_field() ?>
    <div class="form-row-3">
      <div><label>Supplier</label><select name="supplier_id"><option value="">-</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div><label>Invoice No.</label><input name="invoice_no" placeholder="auto"></div>
      <div><label>Date</label><input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>"></div>
    </div>

    <h3 class="mt">Items</h3>
    <div class="table-wrap"><table id="itemsTable">
      <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th style="width:80px">Qty</th>
        <th style="width:110px">Cost</th><th style="width:110px">Sale</th><th></th></tr></thead>
      <tbody>
        <?php for ($r=0;$r<3;$r++): ?>
        <tr>
          <td><select name="medicine_id[]"><option value="">-</option><?= $medOptions ?></select></td>
          <td><input name="batch_no[]"></td>
          <td><input type="date" name="expiry_date[]"></td>
          <td><input type="number" name="quantity[]" min="0"></td>
          <td><input type="number" step="0.01" name="purchase_price[]"></td>
          <td><input type="number" step="0.01" name="sale_price[]"></td>
          <td><button type="button" class="btn btn-sm btn-ghost" onclick="this.closest('tr').remove()">&times;</button></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table></div>
    <button type="button" class="btn btn-sm btn-ghost mt" onclick="addRow()">+ Add Row</button>

    <div class="form-row-3 mt">
      <div><label>Discount</label><input type="number" step="0.01" name="discount" value="0"></div>
      <div><label>Tax</label><input type="number" step="0.01" name="tax" value="0"></div>
      <div><label>Amount Paid</label><input type="number" step="0.01" name="paid" value="0"></div>
    </div>
    <label>Note</label><input name="note">
    <div class="actions"><button class="btn btn-green">Save Purchase</button></div>
  </form>
</div>
<template id="rowTpl">
  <tr>
    <td><select name="medicine_id[]"><option value="">-</option><?= $medOptions ?></select></td>
    <td><input name="batch_no[]"></td>
    <td><input type="date" name="expiry_date[]"></td>
    <td><input type="number" name="quantity[]" min="0"></td>
    <td><input type="number" step="0.01" name="purchase_price[]"></td>
    <td><input type="number" step="0.01" name="sale_price[]"></td>
    <td><button type="button" class="btn btn-sm btn-ghost" onclick="this.closest('tr').remove()">&times;</button></td>
  </tr>
</template>
<script>
function addRow(){
  const t=document.getElementById('rowTpl').content.cloneNode(true);
  document.querySelector('#itemsTable tbody').appendChild(t);
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
