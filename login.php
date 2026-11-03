<?php
// Implements the Login Process Flowchart (Figure 2.3.1):
// enter credentials -> check not empty -> look up user -> verify password (and role)
// -> invalid: error + retry | valid: open dashboard for the user's role.
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Which login view is being shown: ?role=admin or ?role=chv (default chv)
$role = (($_GET['role'] ?? 'chv') === 'admin') ? 'admin' : 'chv';

$views = [
    'admin' => [
        'badge' => 'ADMINISTRATOR',
        'title' => 'Administrator Login',
        'sub'   => 'Authorized personnel only',
        'btn'   => 'Sign in as Administrator',
    ],
    'chv' => [
        'badge' => 'CHV',
        'title' => 'CHV Login',
        'sub'   => 'Community Health Volunteer – enter your username and password',
        'btn'   => 'Sign in as CHV',
    ],
];
$view = $views[$role];

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

                        if ($user && $user['status'] === 'active' && $user['role'] === $role && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header('Location: dashboard.php');
            exit;
        }
        // Same message for every failure so attackers cannot tell which part was wrong.
        $error = 'Invalid username or password.';
    }
}

$pageTitle = $view['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="card login-card role-<?= e($role) ?>">
  <div class="login-tabs">
    <a href="login.php?role=admin" class="<?= $role === 'admin' ? 'active' : '' ?>">Administrator</a>
    <a href="login.php?role=chv" class="<?= $role === 'chv' ? 'active' : '' ?>">CHV</a>
  </div>

  <span class="role-badge"><?= e($view['badge']) ?></span>
  <h1><?= e($view['title']) ?></h1>
  <p class="login-sub"><?= e($view['sub']) ?></p>

  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <button type="submit" class="btn"><?= e($view['btn']) ?></button>

  </form>

  <?php if ($role === 'chv'): ?>
    <p style="text-align:center; margin-top:14px"><a href="forgot_password.php">Forgot password?</a></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>