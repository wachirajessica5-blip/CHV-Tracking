<?php
// Register (no id) or update (?id=) a household. CHV only.
require_once __DIR__ . '/includes/auth.php';
require_role('chv');

$id = (int)($_GET['id'] ?? 0);
$h = ['head_name' => '', 'village' => '', 'phone' => '', 'notes' => ''];
if ($id) {
    $h = load_household($id, true);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $h['head_name'] = trim($_POST['head_name'] ?? '');
    $h['village']   = trim($_POST['village'] ?? '');
    $h['phone']     = trim($_POST['phone'] ?? '');
    $h['notes']     = trim($_POST['notes'] ?? '');

    if ($h['head_name'] === '') $errors[] = 'Head of household name is required.';
    if ($h['village'] === '')   $errors[] = 'Village / location is required.';
    if ($h['phone'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $h['phone'])) {
        $errors[] = 'Phone number looks invalid (digits, spaces, + and - only).';
    }

    if (!$errors) {
        $pdo = db();
        if ($id) {
            $pdo->prepare('UPDATE households SET head_name=?, village=?, phone=?, notes=? WHERE household_id=? AND chv_id=?')
                ->execute([$h['head_name'], $h['village'], $h['phone'] ?: null, $h['notes'] ?: null, $id, $_SESSION['user_id']]);
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO households (household_code, head_name, village, phone, notes, chv_id) VALUES (?,?,?,?,?,?)')
                ->execute([uniqid('TMP'), $h['head_name'], $h['village'], $h['phone'] ?: null, $h['notes'] ?: null, $_SESSION['user_id']]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE households SET household_code=? WHERE household_id=?')
                ->execute(['HH-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]);
            $pdo->commit();
        }
        header('Location: household_view.php?id=' . $id);
        exit;
    }
}

$pageTitle = $id ? 'Edit household' : 'Register household';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($pageTitle) ?></h1>
<div class="card form-card">
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Head of household *</label>
    <input type="text" name="head_name" value="<?= e($h['head_name']) ?>" required>
    <label>Village / location *</label>
    <input type="text" name="village" value="<?= e($h['village']) ?>" required>
    <label>Phone</label>
    <input type="text" name="phone" value="<?= e($h['phone']) ?>">
    <label>Notes</label>
    <textarea name="notes" rows="3"><?= e($h['notes']) ?></textarea>
    <button type="submit" class="btn">Save household</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
