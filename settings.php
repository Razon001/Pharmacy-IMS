<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)$pdo->query('SELECT id FROM settings LIMIT 1')->fetchColumn();

    $data = [
        trim(post('pharmacy_name')) ?: 'My Pharmacy',
        trim(post('address')) ?: null,
        trim(post('phone')) ?: null,
        trim(post('email')) ?: null,
        trim(post('currency')) ?: '$',
        (float)post('tax_rate'),
        trim(post('invoice_prefix')) ?: 'INV',
        trim(post('footer_note')) ?: null,
    ];

    if ($id) {
        $data[] = $id;
        $pdo->prepare('UPDATE settings SET pharmacy_name=?,address=?,phone=?,email=?,currency=?,tax_rate=?,invoice_prefix=?,footer_note=? WHERE id=?')
            ->execute($data);
    } else {
        $pdo->prepare('INSERT INTO settings (pharmacy_name,address,phone,email,currency,tax_rate,invoice_prefix,footer_note) VALUES (?,?,?,?,?,?,?,?)')
            ->execute($data);
    }
    flash('Settings saved.', 'success');
    redirect('settings.php');
}

require_once __DIR__ . '/includes/header.php';
$s = $pdo->query('SELECT * FROM settings LIMIT 1')->fetch() ?: [];
?>
<div class="card" style="max-width:720px">
  <div class="card-head"><h2>Pharmacy Settings</h2></div>
  <form method="post">
    <?= csrf_field() ?>
    <label>Pharmacy name</label>
    <input name="pharmacy_name" required value="<?= e($s['pharmacy_name'] ?? '') ?>">

    <label>Address</label>
    <input name="address" value="<?= e($s['address'] ?? '') ?>">

    <div class="form-row">
      <div><label>Phone</label><input name="phone" value="<?= e($s['phone'] ?? '') ?>"></div>
      <div><label>Email</label><input type="email" name="email" value="<?= e($s['email'] ?? '') ?>"></div>
    </div>

    <div class="form-row">
      <div><label>Currency symbol</label><input name="currency" maxlength="10" value="<?= e($s['currency'] ?? '$') ?>"></div>
      <div><label>Default tax rate (%)</label><input type="number" step="0.01" min="0" name="tax_rate" value="<?= e($s['tax_rate'] ?? '0') ?>"></div>
    </div>

    <div class="form-row">
      <div><label>Invoice prefix</label><input name="invoice_prefix" maxlength="10" value="<?= e($s['invoice_prefix'] ?? 'INV') ?>"></div>
      <div><label>Receipt footer note</label><input name="footer_note" value="<?= e($s['footer_note'] ?? '') ?>"></div>
    </div>

    <div class="actions"><button class="btn btn-green">Save Settings</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
