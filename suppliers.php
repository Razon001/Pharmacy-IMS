<?php
$pageTitle = 'Suppliers';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','pharmacist']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id');
        $d = [trim(post('name')), trim(post('contact_person'))?:null, trim(post('phone'))?:null,
              trim(post('email'))?:null, trim(post('address'))?:null, trim(post('gst_no'))?:null];
        if ($d[0]==='') { flash('Name required.','danger'); }
        elseif ($id) { $d[]=$id; $pdo->prepare('UPDATE suppliers SET name=?,contact_person=?,phone=?,email=?,address=?,gst_no=? WHERE id=?')->execute($d); flash('Supplier updated.','success'); }
        else { $pdo->prepare('INSERT INTO suppliers (name,contact_person,phone,email,address,gst_no) VALUES (?,?,?,?,?,?)')->execute($d); flash('Supplier added.','success'); }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM suppliers WHERE id=?')->execute([(int)post('id')]);
        flash('Supplier deleted.','info');
    }
    redirect('suppliers.php');
}

require_once __DIR__ . '/includes/header.php';
$edit = null;
if ($eid=(int)get('edit')) { $st=$pdo->prepare('SELECT * FROM suppliers WHERE id=?'); $st->execute([$eid]); $edit=$st->fetch(); }
$rows = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
?>
<div class="grid grid-2">
  <div class="card" style="align-self:start">
    <h2><?= $edit?'Edit':'Add' ?> Supplier</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
      <label>Name *</label><input name="name" required value="<?= e($edit['name']??'') ?>">
      <div class="form-row">
        <div><label>Contact Person</label><input name="contact_person" value="<?= e($edit['contact_person']??'') ?>"></div>
        <div><label>Phone</label><input name="phone" value="<?= e($edit['phone']??'') ?>"></div>
      </div>
      <div class="form-row">
        <div><label>Email</label><input type="email" name="email" value="<?= e($edit['email']??'') ?>"></div>
        <div><label>GST / Tax No.</label><input name="gst_no" value="<?= e($edit['gst_no']??'') ?>"></div>
      </div>
      <label>Address</label><input name="address" value="<?= e($edit['address']??'') ?>">
      <div class="actions"><button class="btn btn-green">Save</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="suppliers.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>
  <div class="card">
    <h2>Suppliers</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><strong><?= e($r['name']) ?></strong><br><small class="muted"><?= e($r['email']) ?></small></td>
          <td><?= e($r['contact_person']) ?></td><td><?= e($r['phone']) ?></td>
          <td class="text-right">
            <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-red" data-confirm="Delete supplier?">Del</button></form>
          </td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
