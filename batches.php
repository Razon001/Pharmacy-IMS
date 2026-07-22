<?php
$pageTitle = 'Stock / Batches';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','pharmacist']);
$pdo = db();

$mid = (int)get('id');
$st = $pdo->prepare('SELECT * FROM medicines WHERE id=?'); $st->execute([$mid]);
$med = $st->fetch();
if (!$med) { die('Medicine not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'add_batch') {
        $qty = max(1,(int)post('quantity'));
        $pp  = max(0,(float)post('purchase_price'));
        $sp  = max(0,(float)post('sale_price'));
        $bn  = trim(post('batch_no')) ?: null;
        $exp = post('expiry_date') ?: null;
        $sup = (int)post('supplier_id') ?: null;
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO batches (medicine_id,batch_no,supplier_id,expiry_date,quantity,purchase_price,sale_price) VALUES (?,?,?,?,?,?,?)')
            ->execute([$mid,$bn,$sup,$exp,$qty,$pp,$sp]);
        $bid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO stock_movements (medicine_id,batch_id,type,quantity,reference,note,user_id) VALUES (?,?,?,?,?,?,?)')
            ->execute([$mid,$bid,'in',$qty,$bn,'Manual stock add',current_user()['id']]);
        $pdo->commit();
        flash('Stock added.', 'success');
        redirect('batches.php?id=' . $mid);
    }

    if ($action === 'adjust') {
        $bid = (int)post('batch_id');
        $newQty = max(0,(int)post('quantity'));
        $st = $pdo->prepare('SELECT quantity FROM batches WHERE id=? AND medicine_id=?');
        $st->execute([$bid,$mid]);
        $old = $st->fetchColumn();
        if ($old !== false) {
            $diff = $newQty - (int)$old;
            $pdo->prepare('UPDATE batches SET quantity=? WHERE id=?')->execute([$newQty,$bid]);
            $pdo->prepare('INSERT INTO stock_movements (medicine_id,batch_id,type,quantity,note,user_id) VALUES (?,?,?,?,?,?)')
                ->execute([$mid,$bid,'adjust',$diff,'Manual adjustment',current_user()['id']]);
            flash('Batch adjusted.', 'info');
        }
        redirect('batches.php?id=' . $mid);
    }
}

require_once __DIR__ . '/includes/header.php';
$batches = $pdo->prepare('SELECT b.*, s.name supplier FROM batches b LEFT JOIN suppliers s ON s.id=b.supplier_id WHERE b.medicine_id=? ORDER BY b.expiry_date ASC, b.id');
$batches->execute([$mid]);
$batches = $batches->fetchAll();
$suppliers = $pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$total = array_sum(array_column($batches,'quantity'));
?>
<div class="card-head">
  <h2><?= e($med['name']) ?> &middot; Total Stock: <?= (int)$total ?> <?= e($med['unit']) ?></h2>
  <a class="btn btn-sm btn-ghost" href="medicines.php">Back to Medicines</a>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Add Stock (new batch)</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_batch">
      <div class="form-row">
        <div><label>Batch No.</label><input name="batch_no"></div>
        <div><label>Expiry Date</label><input type="date" name="expiry_date"></div>
      </div>
      <div class="form-row">
        <div><label>Quantity *</label><input type="number" name="quantity" min="1" required></div>
        <div><label>Supplier</label><select name="supplier_id"><option value="">-</option>
          <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="form-row">
        <div><label>Purchase Price *</label><input type="number" step="0.01" name="purchase_price" required></div>
        <div><label>Sale Price *</label><input type="number" step="0.01" name="sale_price" required></div>
      </div>
      <div class="actions"><button class="btn btn-green">Add Stock</button></div>
    </form>
  </div>

  <div class="card">
    <h2>Batches</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Batch</th><th>Expiry</th><th>Supplier</th><th class="text-right">Cost</th><th class="text-right">MRP</th><th style="width:90px">Qty</th></tr></thead>
      <tbody>
      <?php if (!$batches): ?><tr><td colspan="6" class="text-center muted">No stock yet.</td></tr><?php endif; ?>
      <?php foreach ($batches as $b):
        $exp = $b['expiry_date'];
        $expClass = '';
        if ($exp) {
          $days = (strtotime($exp) - time())/86400;
          $expClass = $days < 0 ? 'badge-red' : ($days <= 60 ? 'badge-amber' : 'badge-green');
        } ?>
        <tr>
          <td><?= e($b['batch_no'] ?: '-') ?></td>
          <td><?= $exp?'<span class="badge '.$expClass.'">'.date('d M Y',strtotime($exp)).'</span>':'-' ?></td>
          <td class="muted"><?= e($b['supplier'] ?: '-') ?></td>
          <td class="text-right"><?= money($b['purchase_price']) ?></td>
          <td class="text-right"><?= money($b['sale_price']) ?></td>
          <td>
            <form method="post" style="display:flex;gap:.3rem">
              <?= csrf_field() ?><input type="hidden" name="action" value="adjust">
              <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
              <input type="number" name="quantity" value="<?= (int)$b['quantity'] ?>" min="0" style="padding:.3rem">
              <button class="btn btn-sm btn-ghost">&#10003;</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
