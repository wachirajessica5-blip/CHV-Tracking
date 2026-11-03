<?php
// ONE-TIME SETUP: creates the first administrator account.
// It refuses to run once an admin exists. DELETE this file after use.
require_once __DIR__ . '/includes/auth.php';

$exists = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
$msg = '';
$ok = false;

if (!$exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($name === '' || $user === '' || strlen($pass) < 8) {
        $msg = 'All fields are required and the password must be at least 8 characters.';
    } else {
        $stmt = db()->prepare("INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$name, $user, password_hash($pass, PASSWORD_DEFAULT)]);
        $ok = true;
        $exists = true;
    }
}

$pageTitle = 'Setup';
require __DIR__ . '/includes/header.php';
?>
<div class="card login-card">
  <h1>First-time setup</h1>
  <?php if ($ok): ?>
    <div class="alert success">Administrator created. <strong>Delete setup.php now</strong>, then <a href="login.php">log in</a>.</div>
  <?php elseif ($exists): ?>
    <div class="alert error">An administrator already exists. Delete this file (setup.php).</div>
  <?php else: ?>
    <?php if ($msg): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Full name</label><input type="text" name="full_name" required>
      <label>Username</label><input type="text" name="username" required>
      <label>Password (min 8 characters)</label><input type="password" name="password" required>
      <button type="submit" class="btn">Create administrator</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
