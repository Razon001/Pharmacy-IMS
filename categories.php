<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_role(['admin','pharmacist']);
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id');
        $name = trim(post('name'));
        $desc = trim(post('description')) ?: null;
        if ($name === '') { flash('Name required.', 'danger'); }
        elseif ($id) {
            $pdo->prepare('UPDATE categories SET name=?,description=? WHERE id=?')->execute([$name,$desc,$id]);
            flash('Category updated.', 'success');
        } else {
            try { $pdo->prepare('INSERT INTO categories (name,description) VALUES (?,?)')->execute([$name,$desc]); flash('Category added.', 'success'); }
            catch (PDOException $e) { flash('Category already exists.', 'danger'); }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([(int)post('id')]);
        flash('Category deleted.', 'info');
    }
    redirect('categories.php');
}

require_once __DIR__ . '/includes/header.php';
$edit = null;
if ($eid = (int)get('edit')) { $st=$pdo->prepare('SELECT * FROM categories WHERE id=?'); $st->execute([$eid]); $edit=$st->fetch(); }
$rows = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM medicines m WHERE m.category_id=c.id) cnt FROM categories c ORDER BY c.name')->fetchAll();
?>
<div class="grid grid-2">
  <?php if (has_role(['admin','pharmacist'])): ?>
  <div class="card" style="align-self:start">
    <h2><?= $edit?'Edit':'Add' ?> Category</h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
      <label>Name *</label><input name="name" required value="<?= e($edit['name']??'') ?>">
      <label>Description</label><input name="description" value="<?= e($edit['description']??'') ?>">
      <div class="actions"><button class="btn btn-green">Save</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="categories.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>
  <?php endif; ?>
  <div class="card">
    <h2>Categories</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Description</th><th class="text-right">Medicines</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><strong><?= e($r['name']) ?></strong></td><td class="muted"><?= e($r['description']) ?></td>
          <td class="text-right"><?= (int)$r['cnt'] ?></td>
          <td class="text-right">
          <?php if (has_role(['admin','pharmacist'])): ?>
            <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-red" data-confirm="Delete category?">Del</button></form>
          <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
