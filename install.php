<?php
/**
 * One-time setup: creates (or resets) the default admin account.
 * Run this ONCE in your browser after importing database.sql, then delete it.
 *
 * Reason this exists: bcrypt hashes must be produced by PHP's password_hash(),
 * which MySQL cannot generate inside database.sql.
 */
require_once __DIR__ . '/includes/functions.php';

$defaults = [
    'name'     => 'Administrator',
    'username' => 'admin',
    'password' => 'admin123',
    'role'     => 'admin',
];

try {
    $pdo = db();

    // ensure a settings row exists
    $count = (int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO settings (pharmacy_name) VALUES ('My Pharmacy')");
    }

    $hash = password_hash($defaults['password'], PASSWORD_DEFAULT);

    $exists = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $exists->execute([$defaults['username']]);
    $id = $exists->fetchColumn();

    if ($id) {
        $st = $pdo->prepare('UPDATE users SET password = ?, role = ?, status = 1 WHERE id = ?');
        $st->execute([$hash, $defaults['role'], $id]);
        $action = 'updated (password reset)';
    } else {
        $st = $pdo->prepare('INSERT INTO users (name, username, password, role, status) VALUES (?,?,?,?,1)');
        $st->execute([$defaults['name'], $defaults['username'], $hash, $defaults['role']]);
        $action = 'created';
    }
    $ok = true;
} catch (Throwable $ex) {
    $ok = false;
    $err = $ex->getMessage();
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Installer</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="login-wrap">
<div class="login-card">
  <div class="logo-big">&#9877;</div>
  <h1>Setup</h1>
  <?php if (!empty($ok)): ?>
    <div class="alert alert-success">Admin account <?= e($action) ?> successfully.</div>
    <p><strong>Login details</strong></p>
    <p>Username: <code>admin</code><br>Password: <code>admin123</code></p>
    <div class="alert alert-warning">For security, delete <code>install.php</code> and change this
      password after logging in.</div>
    <a class="btn btn-block" href="index.php">Go to Login</a>
  <?php else: ?>
    <div class="alert alert-danger">Setup failed: <?= e($err ?? 'unknown error') ?><br><br>
      Make sure you imported <code>database.sql</code> and that
      <code>config/database.php</code> has the correct credentials.</div>
  <?php endif; ?>
</div>
</body></html>
