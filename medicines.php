<?php
$pageTitle = 'Medicines';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    csrf_check();
    require_role(['admin','pharmacist']);
    $pdo->prepare('UPDATE medicines SET status=0 WHERE id=?')->execute([(int)post('id')]);
    flash('Medicine archived.', 'info');
    redirect('medicines.php');
}

require_once __DIR__ . '/includes/header.php';

$q = trim(get('q'));
$cat = (int)get('cat');
$sql = "SELECT m.*, c.name category, COALESCE(SUM(b.quantity),0) stock
        FROM medicines m LEFT JOIN categories c ON c.id=m.category_id
        LEFT JOIN batches b ON b.medicine_id=m.id WHERE m.status=1";
$params = [];
if ($q !== '') { $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.barcode = ?)"; array_push($params,"%$q%","%$q%",$q); }
if ($cat) { $sql .= " AND m.category_id=?"; $params[] = $cat; }
$sql .= " GROUP BY m.id ORDER BY m.name";
$st = $pdo->prepare($sql); $st->execute($params);
$meds = $st->fetchAll();
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
?>
<div class="card">
  <form class="card-head" method="get">
    <h2>Medicines</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <input type="text" name="q" placeholder="Search..." value="<?= e($q) ?>">
      <select name="cat"><option value="0">All categories</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm">Filter</button>
      <a class="btn btn-sm" href="medicine_edit.php">+ Add Medicine</a>
    </div>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Generic</th><th>Category</th><th>Rack</th>
      <th class="text-right">Price</th><th class="text-right">Stock</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    <?php if (!$meds): ?><tr><td colspan="8" class="text-center muted">No medicines found.</td></tr><?php endif; ?>
    <?php foreach ($meds as $m):
      $low = $m['stock'] <= $m['reorder_level']; ?>
      <tr>
        <td><strong><?= e($m['name']) ?></strong> <?= $m['prescription_required']?'<span class="badge badge-red">Rx</span>':'' ?></td>
        <td class="muted"><?= e($m['generic_name']) ?></td>
        <td><?= e($m['category'] ?: '-') ?></td>
        <td><?= e($m['rack'] ?: '-') ?></td>
        <td class="text-right"><?= money(medicine_price((int)$m['id'])) ?></td>
        <td class="text-right"><?= (int)$m['stock'] ?> <?= e($m['unit']) ?></td>
        <td><?php if ($m['stock']==0): ?><span class="badge badge-red">Out</span>
            <?php elseif ($low): ?><span class="badge badge-amber">Low</span>
            <?php else: ?><span class="badge badge-green">OK</span><?php endif; ?></td>
        <td class="text-right">
          <a class="btn btn-sm btn-ghost" href="batches.php?id=<?= (int)$m['id'] ?>">Stock</a>
          <a class="btn btn-sm btn-ghost" href="medicine_edit.php?id=<?= (int)$m['id'] ?>">Edit</a>
          <?php if (has_role(['admin','pharmacist'])): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-sm btn-red" data-confirm="Archive this medicine?">Del</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
