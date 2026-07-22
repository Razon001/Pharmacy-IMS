<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim(post('username'));
    $password = post('password');

    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // rehash if needed
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $up = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $up->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
        unset($user['password']);
        $_SESSION['user'] = $user;
        session_regenerate_id(true);
        redirect('dashboard.php');
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &middot; Pharmacy System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-wrap">
<div class="login-card">
  <div class="logo-big">&#9877;</div>
  <h1>Pharmacy System</h1>
  <p class="sub">Sign in to continue</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Username</label>
    <input type="text" name="username" required autofocus value="<?= e(post('username')) ?>">
    <label>Password</label>
    <input type="password" name="password" required>
    <div class="actions">
      <button class="btn btn-block" type="submit">Sign In</button>
    </div>
  </form>
  <p class="hint">First time? Run <code>install.php</code> &middot; default: admin / admin123</p>
</div>
</body></html>
