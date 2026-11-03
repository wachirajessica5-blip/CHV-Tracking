<?php
// Administrator: view CHV accounts, create new ones, activate/deactivate.
require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$errors = [];
$success = '';
$form = ['full_name' => '', 'username' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $form['full_name'] = trim($_POST['full_name'] ?? '');
        $form['username']  = trim($_POST['username'] ?? '');
        $form['phone']     = trim($_POST['phone'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($form['full_name'] === '' || $form['username'] === '') $errors[] = 'Full name and username are required.';
        if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($form['phone'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $form['phone'])) $errors[] = 'Phone number looks invalid.';

        if (!$errors) {
            $chk = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $chk->execute([$form['username']]);
            if ($chk->fetchColumn() > 0) {
                $errors[] = 'That username is already taken.';
            } else {
                db()->prepare("INSERT INTO users (full_name, username, phone, password_hash, role) VALUES (?,?,?,?, 'chv')")
                    ->execute([$form['full_name'], $form['username'], $form['phone'] ?: null, password_hash($pass, PASSWORD_DEFAULT)]);
                $success = 'CHV account created.';
                $form = ['full_name' => '', 'username' => '', 'phone' => ''];
            }
        }
    } elseif ($action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        db()->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE user_id = ? AND role = 'chv'")
            ->execute([$uid]);
        $success = 'CHV status updated.';
    }
}

$chvs = db()->query(
    "SELECT u.*, (SELECT COUNT(*) FROM households h WHERE h.chv_id = u.user_id) AS hh
     FROM users u WHERE u.role = 'chv' ORDER BY u.created_at DESC"
)->fetchAll();

$pageTitle = 'CHV Accounts';
require __DIR__ . '/includes/header.php';
?>
<h1>CHV Accounts</h1>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

<div class="card form-card">
  <h2>Add a CHV</h2>
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label>Full name *</label><input type="text" name="full_name" value="<?= e($form['full_name']) ?>" required>
    <label>Username *</label><input type="text" name="username" value="<?= e($form['username']) ?>" required>
    <label>Phone</label><input type="text" name="phone" value="<?= e($form['phone']) ?>">
    <label>Temporary password * (min 8 characters)</label><input type="password" name="password" required>
    <button type="submit" class="btn">Create CHV account</button>
  </form>
</div>

<h2 style="margin-top:24px">Existing CHVs</h2>
<div class="table-wrap">
<table>
  <tr><th>Name</th><th>Username</th><th>Phone</th><th>Households</th><th>Status</th><th></th></tr>
  <?php if (!$chvs): ?><tr><td colspan="6" class="muted">No CHV accounts yet.</td></tr><?php endif; ?>
  <?php foreach ($chvs as $c): ?>
  <tr>
    <td><?= e($c['full_name']) ?></td>
    <td><?= e($c['username']) ?></td>
    <td><?= e($c['phone'] ?: '-') ?></td>
    <td><?= (int)$c['hh'] ?></td>
    <td><?= e(ucfirst($c['status'])) ?></td>
    <td>
      <form method="post" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="user_id" value="<?= (int)$c['user_id'] ?>">
        <button type="submit" class="btn small"><?= $c['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
