<?php
$pageTitle = 'Users';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin']);
$pdo = db();

function admin_count(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status=1")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    $me = current_user();

    if ($action === 'save') {
        $id       = (int)post('id');
        $name     = trim(post('name'));
        $username = trim(post('username'));
        $email    = trim(post('email')) ?: null;
        $phone    = trim(post('phone')) ?: null;
        $role     = post('role');
        $status   = (int)post('status');
        $password = (string)post('password');

        if (!in_array($role, ['admin', 'pharmacist', 'cashier'], true)) {
            $role = 'cashier';
        }
        if ($name === '' || $username === '') {
            flash('Name and username are required.', 'danger');
            redirect('users.php');
        }

        // Prevent removing the last active admin (by demotion or deactivation).
        if ($id) {
            $cur = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $cur->execute([$id]);
            $cur = $cur->fetch();
            if ($cur && $cur['role'] === 'admin' && $cur['status'] == 1
                && ($role !== 'admin' || $status !== 1) && admin_count($pdo) <= 1) {
                flash('You cannot demote or deactivate the only active admin.', 'danger');
                redirect('users.php');
            }
        }

        // Unique username check.
        $chk = $pdo->prepare('SELECT id FROM users WHERE username=? AND id<>?');
        $chk->execute([$username, $id]);
        if ($chk->fetch()) {
            flash('That username is already taken.', 'danger');
            redirect('users.php');
        }

        try {
            if ($id) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE users SET name=?,username=?,email=?,phone=?,role=?,status=?,password=? WHERE id=?')
                        ->execute([$name, $username, $email, $phone, $role, $status, $hash, $id]);
                } else {
                    $pdo->prepare('UPDATE users SET name=?,username=?,email=?,phone=?,role=?,status=? WHERE id=?')
                        ->execute([$name, $username, $email, $phone, $role, $status, $id]);
                }
                flash('User updated.', 'success');
            } else {
                if ($password === '') {
                    flash('Password is required for a new user.', 'danger');
                    redirect('users.php');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (name,username,email,phone,role,status,password) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name, $username, $email, $phone, $role, $status, $hash]);
                flash('User created.', 'success');
            }
        } catch (PDOException $ex) {
            flash('Could not save user: ' . $ex->getMessage(), 'danger');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        if ($id === (int)$me['id']) {
            flash('You cannot delete your own account.', 'danger');
        } else {
            $cur = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $cur->execute([$id]);
            $cur = $cur->fetch();
            if ($cur && $cur['role'] === 'admin' && $cur['status'] == 1 && admin_count($pdo) <= 1) {
                flash('You cannot delete the only active admin.', 'danger');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                flash('User deleted.', 'info');
            }
        }
    }
    redirect('users.php');
}

require_once __DIR__ . '/includes/header.php';

$edit = null;
if ($eid = (int)get('edit')) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([$eid]);
    $edit = $st->fetch();
}
$rows = $pdo->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
$roleBadge = ['admin' => 'badge-red', 'pharmacist' => 'badge-blue', 'cashier' => 'badge-gray'];
?>
<div class="grid grid-2">
  <div class="card" style="align-self:start">
    <h2><?= $edit ? 'Edit' : 'Add' ?> User</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label>Full name *</label>
      <input name="name" required value="<?= e($edit['name'] ?? '') ?>">
      <div class="form-row">
        <div><label>Username *</label><input name="username" required value="<?= e($edit['username'] ?? '') ?>"></div>
        <div>
          <label>Role</label>
          <select name="role">
            <?php foreach (['cashier', 'pharmacist', 'admin'] as $r): ?>
              <option value="<?= $r ?>" <?= (($edit['role'] ?? 'cashier') === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div><label>Email</label><input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
        <div><label>Phone</label><input name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div>
          <label>Password <?= $edit ? '(leave blank to keep)' : '*' ?></label>
          <input type="password" name="password" autocomplete="new-password" <?= $edit ? '' : 'required' ?>>
        </div>
        <div>
          <label>Status</label>
          <select name="status">
            <option value="1" <?= (($edit['status'] ?? 1) == 1) ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (($edit['status'] ?? 1) == 0) ? 'selected' : '' ?>>Disabled</option>
          </select>
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-green">Save</button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="users.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2>Staff Accounts</h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-right">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td><?= e($r['username']) ?></td>
            <td><span class="badge <?= $roleBadge[$r['role']] ?? 'badge-gray' ?>"><?= e(ucfirst($r['role'])) ?></span></td>
            <td>
              <?php if ($r['status']): ?><span class="badge badge-green">Active</span>
              <?php else: ?><span class="badge badge-gray">Disabled</span><?php endif; ?>
            </td>
            <td><?= $r['last_login'] ? e(date('d M Y H:i', strtotime($r['last_login']))) : '&mdash;' ?></td>
            <td class="text-right">
              <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-red" data-confirm="Delete this user?">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
