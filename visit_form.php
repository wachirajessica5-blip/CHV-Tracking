<?php
// Plan or record a visit (add), or update one (?id=). CHV only, own households only.
require_once __DIR__ . '/includes/auth.php';
require_role('chv');

$uid = $_SESSION['user_id'];
$visitId = (int)($_GET['id'] ?? 0);
$v = [
    'household_id' => (int)($_GET['household_id'] ?? 0),
    'visit_date' => date('Y-m-d'), 'status' => 'completed',
    'notes' => '', 'next_visit_date' => '',
];

if ($visitId) {
    $s = db()->prepare('SELECT * FROM visits WHERE visit_id = ? AND chv_id = ?');
    $s->execute([$visitId, $uid]);
    $v = $s->fetch();
    if (!$v) { http_response_code(404); die('Visit not found, or it belongs to another CHV.'); }
    $v['next_visit_date'] = (string)$v['next_visit_date'];
}

$hs = db()->prepare('SELECT household_id, household_code, head_name FROM households WHERE chv_id = ? ORDER BY household_code');
$hs->execute([$uid]);
$households = $hs->fetchAll();
$ownedIds = array_map('intval', array_column($households, 'household_id'));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$visitId) $v['household_id'] = (int)($_POST['household_id'] ?? 0);
    $v['visit_date']      = $_POST['visit_date'] ?? '';
    $v['status']          = $_POST['status'] ?? '';
    $v['notes']           = trim($_POST['notes'] ?? '');
    $v['next_visit_date'] = trim($_POST['next_visit_date'] ?? '');

    if (!in_array((int)$v['household_id'], $ownedIds, true)) $errors[] = 'Select one of your households.';
    if (!valid_date($v['visit_date'])) $errors[] = 'Enter a valid visit date.';
    if (!in_array($v['status'], ['planned', 'completed', 'missed'], true)) $errors[] = 'Select a status.';
    if ($v['status'] === 'completed' && valid_date($v['visit_date']) && $v['visit_date'] > date('Y-m-d')) {
        $errors[] = 'A visit in the future cannot be marked completed. Use "Planned".';
    }
    if ($v['next_visit_date'] !== '') {
        if (!valid_date($v['next_visit_date'])) $errors[] = 'Enter a valid next visit date.';
        elseif (valid_date($v['visit_date']) && $v['next_visit_date'] <= $v['visit_date']) {
            $errors[] = 'Next visit date must be after the visit date.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $next = $v['next_visit_date'] ?: null;
        if ($visitId) {
            $pdo->prepare('UPDATE visits SET visit_date=?, status=?, notes=?, next_visit_date=? WHERE visit_id=? AND chv_id=?')
                ->execute([$v['visit_date'], $v['status'], $v['notes'] ?: null, $next, $visitId, $uid]);
        } else {
            $pdo->prepare('INSERT INTO visits (household_id, chv_id, visit_date, status, notes, next_visit_date) VALUES (?,?,?,?,?,?)')
                ->execute([$v['household_id'], $uid, $v['visit_date'], $v['status'], $v['notes'] ?: null, $next]);
        }
        // Follow-up scheduling: a completed visit with a next date creates a planned visit,
        // unless one is already planned for that household on that date.
        if ($v['status'] === 'completed' && $next) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE household_id=? AND visit_date=? AND status='planned'");
            $chk->execute([$v['household_id'], $next]);
            if ((int)$chk->fetchColumn() === 0) {
                $pdo->prepare("INSERT INTO visits (household_id, chv_id, visit_date, status, notes) VALUES (?,?,?, 'planned', 'Scheduled follow-up')")
                    ->execute([$v['household_id'], $uid, $next]);
            }
        }
        header('Location: visits.php?filter=all');
        exit;
    }
}

$pageTitle = $visitId ? 'Update visit' : 'Record / plan visit';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($pageTitle) ?></h1>
<div class="card form-card">
<?php if (!$households): ?>
  <p>You have no households yet. <a href="household_form.php">Register one first</a>.</p>
<?php else: ?>
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Household *</label>
    <select name="household_id" <?= $visitId ? 'disabled' : 'required' ?>>
      <option value="">Select...</option>
      <?php foreach ($households as $h): ?>
        <option value="<?= (int)$h['household_id'] ?>" <?= (int)$v['household_id'] === (int)$h['household_id'] ? 'selected' : '' ?>>
          <?= e($h['household_code'] . ' - ' . $h['head_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label>Visit date *</label>
    <input type="date" name="visit_date" value="<?= e($v['visit_date']) ?>" required>
    <label>Status *</label>
    <select name="status" required>
      <?php foreach (['planned' => 'Planned', 'completed' => 'Completed', 'missed' => 'Missed'] as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $v['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <label>Notes</label>
    <textarea name="notes" rows="3"><?= e($v['notes']) ?></textarea>
    <label>Next visit date (optional)</label>
    <input type="date" name="next_visit_date" value="<?= e($v['next_visit_date']) ?>">
    <button type="submit" class="btn">Save visit</button>
  </form>
  <p class="muted">If you complete a visit and set a next visit date, a planned follow-up is created automatically.</p>
<?php endif; ?>
  <p><a href="visits.php">Cancel</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
