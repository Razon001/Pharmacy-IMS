<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id');
        $d = [trim(post('name')), trim(post('phone'))?:null, trim(post('email'))?:null, trim(post('address'))?:null];
        if ($d[0]==='') { flash('Name required.','danger'); }
        elseif ($id) { $d[]=$id; $pdo->prepare('UPDATE customers SET name=?,phone=?,email=?,address=? WHERE id=?')->execute($d); flash('Customer updated.','success'); }
        else { $pdo->prepare('INSERT INTO customers (name,phone,email,address) VALUES (?,?,?,?)')->execute($d); flash('Customer added.','success'); }
    } elseif ($action === 'delete') {
        require_role(['admin','pharmacist']);
        $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([(int)post('id')]);
        flash('Customer deleted.','info');
    }
    redirect('customers.php');
}

require_once __DIR__ . '/includes/header.php';
$edit = null;
if ($eid=(int)get('edit')) { $st=$pdo->prepare('SELECT * FROM customers WHERE id=?'); $st->execute([$eid]); $edit=$st->fetch(); }
$q = trim(get('q'));
$sql = 'SELECT c.*, COUNT(s.id) bills, COALESCE(SUM(s.total),0) spent
        FROM customers c LEFT JOIN sales s ON s.customer_id=c.id AND s.status="completed"';
$params=[];
if ($q!=='') { $sql.=' WHERE c.name LIKE ? OR c.phone LIKE ?'; $params=["%$q%","%$q%"]; }
$sql.=' GROUP BY c.id ORDER BY c.name';
$st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
?>
<div class="grid grid-2">
  <div class="card" style="align-self:start">
    <h2><?= $edit?'Edit':'Add' ?> Customer</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
      <label>Name *</label><input name="name" required value="<?= e($edit['name']??'') ?>">
      <div class="form-row">
        <div><label>Phone</label><input name="phone" value="<?= e($edit['phone']??'') ?>"></div>
        <div><label>Email</label><input type="email" name="email" value="<?= e($edit['email']??'') ?>"></div>
      </div>
      <label>Address</label><input name="address" value="<?= e($edit['address']??'') ?>">
      <div class="actions"><button class="btn btn-green">Save</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="customers.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>
  <div class="card">
    <form class="card-head" method="get"><h2>Customers</h2>
      <div style="display:flex;gap:.5rem"><input name="q" placeholder="Search..." value="<?= e($q) ?>"><button class="btn btn-sm">Go</button></div>
    </form>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Phone</th><th class="text-right">Bills</th><th class="text-right">Spent</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><strong><?= e($r['name']) ?></strong></td><td><?= e($r['phone']) ?></td>
          <td class="text-right"><?= (int)$r['bills'] ?></td><td class="text-right"><?= money($r['spent']) ?></td>
          <td class="text-right">
            <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
            <?php if (has_role(['admin','pharmacist'])): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-red" data-confirm="Delete customer?">Del</button></form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
