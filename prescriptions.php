<?php
$pageTitle = 'Prescriptions';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

const PRESC_DIR = __DIR__ . '/uploads';
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'save') {
        $customerId = (int)post('customer_id') ?: null;
        $patient    = trim(post('patient_name')) ?: null;
        $doctor     = trim(post('doctor_name')) ?: null;
        $notes      = trim(post('notes')) ?: null;
        $stored     = null;

        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                flash('Only images or PDF files are allowed.', 'danger');
                redirect('prescriptions.php');
            }
            if ($_FILES['file']['size'] > 8 * 1024 * 1024) {
                flash('File too large (max 8 MB).', 'danger');
                redirect('prescriptions.php');
            }
            if (!is_dir(PRESC_DIR)) {
                @mkdir(PRESC_DIR, 0755, true);
            }
            $stored = 'presc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], PRESC_DIR . '/' . $stored)) {
                flash('Could not save the uploaded file.', 'danger');
                redirect('prescriptions.php');
            }
        } elseif (!empty($_FILES['file']['name'])) {
            flash('Upload failed. Please try again.', 'danger');
            redirect('prescriptions.php');
        }

        if (!$patient && !$doctor && !$stored && !$notes) {
            flash('Enter at least a patient name, doctor, note, or file.', 'danger');
            redirect('prescriptions.php');
        }

        $pdo->prepare('INSERT INTO prescriptions (customer_id,patient_name,doctor_name,notes,file) VALUES (?,?,?,?,?)')
            ->execute([$customerId, $patient, $doctor, $notes, $stored]);
        flash('Prescription saved.', 'success');
    } elseif ($action === 'delete') {
        require_role(['admin', 'pharmacist']);
        $id = (int)post('id');
        $st = $pdo->prepare('SELECT file FROM prescriptions WHERE id=?');
        $st->execute([$id]);
        $file = $st->fetchColumn();
        if ($file) {
            $path = realpath(PRESC_DIR . '/' . $file);
            if ($path && str_starts_with($path, realpath(PRESC_DIR))) {
                @unlink($path);
            }
        }
        $pdo->prepare('DELETE FROM prescriptions WHERE id=?')->execute([$id]);
        flash('Prescription deleted.', 'info');
    }
    redirect('prescriptions.php');
}

require_once __DIR__ . '/includes/header.php';

$customers = $pdo->query('SELECT id,name FROM customers ORDER BY name')->fetchAll();
$rows = $pdo->query(
    'SELECT p.*, c.name AS customer_name
     FROM prescriptions p LEFT JOIN customers c ON c.id=p.customer_id
     ORDER BY p.created_at DESC'
)->fetchAll();
?>
<div class="grid grid-2">
  <div class="card" style="align-self:start">
    <h2>New Prescription</h2>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <label>Customer</label>
      <select name="customer_id">
        <option value="">— Walk-in / none —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-row">
        <div><label>Patient name</label><input name="patient_name"></div>
        <div><label>Doctor name</label><input name="doctor_name"></div>
      </div>
      <label>Notes</label>
      <textarea name="notes" rows="3"></textarea>
      <label>Scan / photo (jpg, png, pdf — max 8 MB)</label>
      <input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
      <div class="actions"><button class="btn btn-green">Save Prescription</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2>Recent Prescriptions</h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date</th><th>Patient</th><th>Customer</th><th>Doctor</th><th>File</th><th class="text-right">Actions</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="muted">No prescriptions recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
            <td><strong><?= e($r['patient_name'] ?: '—') ?></strong>
              <?php if ($r['notes']): ?><br><small class="muted"><?= e(mb_strimwidth($r['notes'], 0, 60, '…')) ?></small><?php endif; ?>
            </td>
            <td><?= e($r['customer_name'] ?: '—') ?></td>
            <td><?= e($r['doctor_name'] ?: '—') ?></td>
            <td>
              <?php if ($r['file']): ?>
                <a class="btn btn-sm btn-ghost" href="uploads/<?= e(rawurlencode($r['file'])) ?>" target="_blank" rel="noopener">Open</a>
              <?php else: ?>&mdash;<?php endif; ?>
            </td>
            <td class="text-right">
              <?php if (has_role(['admin', 'pharmacist'])): ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-red" data-confirm="Delete this prescription?">Del</button>
              </form>
              <?php else: ?>&mdash;<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
