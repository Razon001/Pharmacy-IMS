<?php
$pageTitle = 'Medicine';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','pharmacist']);
$pdo = db();

$id = (int)get('id');
$med = ['name'=>'','generic_name'=>'','category_id'=>'','barcode'=>'','manufacturer'=>'',
        'unit'=>'pcs','rack'=>'','reorder_level'=>10,'prescription_required'=>0,'description'=>''];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM medicines WHERE id=?'); $st->execute([$id]);
    $med = $st->fetch() ?: $med;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        trim(post('name')), trim(post('generic_name')) ?: null,
        ((int)post('category_id')) ?: null, trim(post('barcode')) ?: null,
        trim(post('manufacturer')) ?: null, trim(post('unit')) ?: 'pcs',
        trim(post('rack')) ?: null, max(0,(int)post('reorder_level')),
        post('prescription_required') ? 1 : 0, trim(post('description')) ?: null,
    ];
    if (trim(post('name')) === '') {
        flash('Name is required.', 'danger');
    } elseif ($id) {
        $sql = 'UPDATE medicines SET name=?,generic_name=?,category_id=?,barcode=?,manufacturer=?,unit=?,rack=?,reorder_level=?,prescription_required=?,description=? WHERE id=?';
        $data[] = $id;
        $pdo->prepare($sql)->execute($data);
        flash('Medicine updated.', 'success');
        redirect('medicines.php');
    } else {
        $sql = 'INSERT INTO medicines (name,generic_name,category_id,barcode,manufacturer,unit,rack,reorder_level,prescription_required,description) VALUES (?,?,?,?,?,?,?,?,?,?)';
        $pdo->prepare($sql)->execute($data);
        $newId = (int)$pdo->lastInsertId();
        flash('Medicine created. Now add stock.', 'success');
        redirect('batches.php?id=' . $newId);
    }
}

require_once __DIR__ . '/includes/header.php';
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
?>
<div class="card" style="max-width:820px">
  <div class="card-head"><h2><?= $id?'Edit':'Add' ?> Medicine</h2><a class="btn btn-sm btn-ghost" href="medicines.php">Back</a></div>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-row">
      <div><label>Name *</label><input name="name" required value="<?= e($med['name']) ?>"></div>
      <div><label>Generic Name</label><input name="generic_name" value="<?= e($med['generic_name']) ?>"></div>
    </div>
    <div class="form-row-3">
      <div><label>Category</label><select name="category_id"><option value="">-</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $med['category_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Manufacturer</label><input name="manufacturer" value="<?= e($med['manufacturer']) ?>"></div>
      <div><label>Barcode</label><input name="barcode" value="<?= e($med['barcode']) ?>"></div>
    </div>
    <div class="form-row-3">
      <div><label>Unit</label><input name="unit" value="<?= e($med['unit']) ?>" placeholder="strip / bottle / pcs"></div>
      <div><label>Rack / Shelf</label><input name="rack" value="<?= e($med['rack']) ?>"></div>
      <div><label>Reorder Level</label><input type="number" name="reorder_level" value="<?= (int)$med['reorder_level'] ?>"></div>
    </div>
    <label><input type="checkbox" name="prescription_required" style="width:auto" <?= $med['prescription_required']?'checked':'' ?>> Prescription required (Rx)</label>
    <label>Description</label><textarea name="description" rows="3"><?= e($med['description']) ?></textarea>
    <div class="actions">
      <button class="btn btn-green" type="submit">Save</button>
      <a class="btn btn-ghost" href="medicines.php">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
