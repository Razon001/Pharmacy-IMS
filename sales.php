<?php
$pageTitle = 'Sales';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

/* Handle return (restocks items, marks sale returned) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'return') {
    csrf_check();
    require_role(['admin','pharmacist']);
    $sid = (int)post('sale_id');
    $reason = trim(post('reason'));
    $st = $pdo->prepare("SELECT * FROM sales WHERE id=? AND status='completed'");
    $st->execute([$sid]);
    $sale = $st->fetch();
    if ($sale) {
        try {
            $pdo->beginTransaction();
            $items = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id=?');
            $items->execute([$sid]);
            foreach ($items->fetchAll() as $it) {
                if ($it['batch_id']) {
                    $pdo->prepare('UPDATE batches SET quantity=quantity+? WHERE id=?')
                        ->execute([$it['quantity'], $it['batch_id']]);
                }
                $pdo->prepare(
                  'INSERT INTO stock_movements (medicine_id,batch_id,type,quantity,reference,note,user_id)
                   VALUES (?,?,?,?,?,?,?)'
                )->execute([$it['medicine_id'],$it['batch_id'],'return',$it['quantity'],$sale['invoice_no'],'Sale return',current_user()['id']]);
            }
            $pdo->prepare("UPDATE sales SET status='returned' WHERE id=?")->execute([$sid]);
            $pdo->prepare('INSERT INTO sale_returns (sale_id,user_id,amount,reason) VALUES (?,?,?,?)')
                ->execute([$sid,current_user()['id'],$sale['total'],$reason ?: null]);
            $pdo->commit();
            flash('Sale ' . $sale['invoice_no'] . ' returned and stock restored.', 'success');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('Return failed: ' . $ex->getMessage(), 'danger');
        }
    }
    redirect('sales.php');
}

require_once __DIR__ . '/includes/header.php';

$from = get('from', date('Y-m-01'));
$to   = get('to', date('Y-m-d'));
$q    = trim(get('q'));

$sql = "SELECT s.*, c.name customer, u.name cashier FROM sales s
        LEFT JOIN customers c ON c.id=s.customer_id
        LEFT JOIN users u ON u.id=s.user_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$from, $to];
if ($q !== '') { $sql .= " AND s.invoice_no LIKE ?"; $params[] = "%$q%"; }
$sql .= " ORDER BY s.id DESC LIMIT 300";
$st = $pdo->prepare($sql); $st->execute($params);
$sales = $st->fetchAll();

$sumTotal = array_sum(array_map(fn($r)=>$r['status']==='completed'?$r['total']:0, $sales));
$sumProfit= array_sum(array_map(fn($r)=>$r['status']==='completed'?$r['profit']:0, $sales));
?>
<div class="card">
  <form class="card-head" method="get">
    <h2>Sales</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <input type="date" name="from" value="<?= e($from) ?>">
      <input type="date" name="to" value="<?= e($to) ?>">
      <input type="text" name="q" placeholder="Invoice #" value="<?= e($q) ?>">
      <button class="btn btn-sm">Filter</button>
      <a class="btn btn-sm" href="pos.php">+ New Sale</a>
    </div>
  </form>
  <div class="grid grid-3 mb">
    <div class="card stat"><div class="icon bg-blue">#</div><div><div class="n"><?= count($sales) ?></div><div class="l">Bills</div></div></div>
    <div class="card stat"><div class="icon bg-green">&#128181;</div><div><div class="n"><?= money($sumTotal) ?></div><div class="l">Revenue</div></div></div>
    <div class="card stat"><div class="icon bg-violet">&#128176;</div><div><div class="n"><?= money($sumProfit) ?></div><div class="l">Profit</div></div></div>
  </div>

  <div class="table-wrap"><table>
    <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Cashier</th><th>Pay</th>
      <th class="text-right">Total</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    <?php if (!$sales): ?><tr><td colspan="8" class="text-center muted">No sales in this range.</td></tr><?php endif; ?>
    <?php foreach ($sales as $s): ?>
      <tr>
        <td><a href="invoice.php?id=<?= (int)$s['id'] ?>"><?= e($s['invoice_no']) ?></a></td>
        <td><?= date('d M Y H:i', strtotime($s['sale_date'])) ?></td>
        <td><?= e($s['customer'] ?: 'Walk-in') ?></td>
        <td><?= e($s['cashier'] ?: '-') ?></td>
        <td><span class="badge badge-gray"><?= e(strtoupper($s['payment_method'])) ?></span></td>
        <td class="text-right"><?= money($s['total']) ?></td>
        <td><?php if ($s['status']==='returned'): ?><span class="badge badge-red">Returned</span>
            <?php else: ?><span class="badge badge-green">Completed</span><?php endif; ?></td>
        <td class="text-right">
          <a class="btn btn-sm btn-ghost" href="invoice.php?id=<?= (int)$s['id'] ?>">View</a>
          <?php if ($s['status']==='completed' && has_role(['admin','pharmacist'])): ?>
          <button class="btn btn-sm btn-red" onclick="document.getElementById('ret<?= (int)$s['id'] ?>').style.display='block'">Return</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($s['status']==='completed' && has_role(['admin','pharmacist'])): ?>
      <tr id="ret<?= (int)$s['id'] ?>" style="display:none">
        <td colspan="8">
          <form method="post" style="display:flex;gap:.5rem;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="return">
            <input type="hidden" name="sale_id" value="<?= (int)$s['id'] ?>">
            <input type="text" name="reason" placeholder="Reason for return" style="max-width:320px">
            <button class="btn btn-sm btn-red" data-confirm="Return this sale and restock all items?">Confirm Return</button>
          </form>
        </td>
      </tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
