<?php
// Record (?member_id=) or update (?id=) a vaccination milestone. CHV only, own households only.
require_once __DIR__ . '/includes/auth.php';
require_role('chv');

$uid = $_SESSION['user_id'];
$vacId = (int)($_GET['id'] ?? 0);
$v = ['vaccine_name' => '', 'due_date' => '', 'date_given' => '', 'status' => 'due'];

if ($vacId) {
    $s = db()->prepare('SELECT * FROM vaccinations WHERE vaccination_id = ?');
    $s->execute([$vacId]);
    $v = $s->fetch();
    if (!$v) { http_response_code(404); die('Vaccination record not found.'); }
    $v['date_given'] = (string)$v['date_given'];
    $memberId = (int)$v['member_id'];
} else {
    $memberId = (int)($_GET['member_id'] ?? 0);
}
$member = load_member_owned($memberId); // enforces ownership

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['vaccine_name'] = trim($_POST['vaccine_name'] ?? '');
    $v['due_date']     = $_POST['due_date'] ?? '';
    $v['status']       = $_POST['status'] ?? '';
    $v['date_given']   = trim($_POST['date_given'] ?? '');

    if ($v['vaccine_name'] === '') $errors[] = 'Vaccine name is required.';
    if (!valid_date($v['due_date'])) $errors[] = 'Enter a valid due date.';
    if (!in_array($v['status'], ['due', 'given', 'missed'], true)) $errors[] = 'Select a status.';
    if ($v['status'] === 'given') {
        if (!valid_date($v['date_given'])) $errors[] = 'Enter the date the vaccine was given.';
        elseif ($v['date_given'] > date('Y-m-d')) $errors[] = 'Date given cannot be in the future.';
    }

    if (!$errors) {
        $given = $v['status'] === 'given' ? $v['date_given'] : null;
        if ($vacId) {
            db()->prepare('UPDATE vaccinations SET vaccine_name=?, due_date=?, status=?, date_given=? WHERE vaccination_id=?')
                ->execute([$v['vaccine_name'], $v['due_date'], $v['status'], $given, $vacId]);
        } else {
            db()->prepare('INSERT INTO vaccinations (member_id, vaccine_name, due_date, date_given, status, recorded_by) VALUES (?,?,?,?,?,?)')
                ->execute([$memberId, $v['vaccine_name'], $v['due_date'], $given, $v['status'], $uid]);
        }
        header('Location: household_view.php?id=' . (int)$member['household_id']);
        exit;
    }
}

$pageTitle = $vacId ? 'Update vaccination' : 'Record vaccination';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($pageTitle) ?></h1>
<p class="muted">Member: <strong><?= e($member['full_name']) ?></strong> (<?= e($member['household_code']) ?>)</p>
<div class="card form-card">
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Vaccine / milestone *</label>
    <input type="text" name="vaccine_name" list="vaccines" value="<?= e($v['vaccine_name']) ?>" required>
    <datalist id="vaccines">
      <option value="BCG"><option value="OPV"><option value="Pentavalent"><option value="PCV10">
      <option value="Rotavirus"><option value="IPV"><option value="Measles-Rubella"><option value="Yellow fever">
      <option value="Vitamin A"><option value="HPV">
    </datalist>
    <label>Due date *</label>
    <input type="date" name="due_date" value="<?= e($v['due_date']) ?>" required>
    <label>Status *</label>
    <select name="status" required>
      <?php foreach (['due' => 'Due', 'given' => 'Given', 'missed' => 'Missed'] as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $v['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <label>Date given (only if status is Given)</label>
    <input type="date" name="date_given" max="<?= date('Y-m-d') ?>" value="<?= e($v['date_given']) ?>">
    <button type="submit" class="btn">Save</button>
  </form>
  <p><a href="household_view.php?id=<?= (int)$member['household_id'] ?>">Cancel</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
