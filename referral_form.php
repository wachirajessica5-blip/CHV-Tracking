<?php
// Make (?member_id=) or update (?id=) a referral. CHV only, own households only.
require_once __DIR__ . '/includes/auth.php';
require_role('chv');

$uid = $_SESSION['user_id'];
$refId = (int)($_GET['id'] ?? 0);
$r = ['referral_date' => date('Y-m-d'), 'facility' => '', 'reason' => '', 'status' => 'pending'];

if ($refId) {
    $s = db()->prepare('SELECT * FROM referrals WHERE referral_id = ? AND chv_id = ?');
    $s->execute([$refId, $uid]);
    $r = $s->fetch();
    if (!$r) { http_response_code(404); die('Referral not found, or it belongs to another CHV.'); }
    $memberId = (int)$r['member_id'];
} else {
    $memberId = (int)($_GET['member_id'] ?? 0);
}
$member = load_member_owned($memberId);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r['referral_date'] = $_POST['referral_date'] ?? '';
    $r['facility']      = trim($_POST['facility'] ?? '');
    $r['reason']        = trim($_POST['reason'] ?? '');
    $r['status']        = $_POST['status'] ?? '';

    if (!valid_date($r['referral_date'])) $errors[] = 'Enter a valid referral date.';
    elseif ($r['referral_date'] > date('Y-m-d')) $errors[] = 'Referral date cannot be in the future.';
    if ($r['facility'] === '') $errors[] = 'Health facility is required.';
    if ($r['reason'] === '')   $errors[] = 'Reason for referral is required.';
    if (!in_array($r['status'], ['pending', 'attended', 'not_attended'], true)) $errors[] = 'Select a status.';

    if (!$errors) {
        if ($refId) {
            db()->prepare('UPDATE referrals SET referral_date=?, facility=?, reason=?, status=? WHERE referral_id=? AND chv_id=?')
                ->execute([$r['referral_date'], $r['facility'], $r['reason'], $r['status'], $refId, $uid]);
        } else {
            db()->prepare('INSERT INTO referrals (member_id, chv_id, referral_date, facility, reason, status) VALUES (?,?,?,?,?,?)')
                ->execute([$memberId, $uid, $r['referral_date'], $r['facility'], $r['reason'], $r['status']]);
        }
        header('Location: household_view.php?id=' . (int)$member['household_id']);
        exit;
    }
}

$pageTitle = $refId ? 'Update referral' : 'Make referral';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($pageTitle) ?></h1>
<p class="muted">Member: <strong><?= e($member['full_name']) ?></strong> (<?= e($member['household_code']) ?>)</p>
<div class="card form-card">
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Referral date *</label>
    <input type="date" name="referral_date" max="<?= date('Y-m-d') ?>" value="<?= e($r['referral_date']) ?>" required>
    <label>Health facility *</label>
    <input type="text" name="facility" value="<?= e($r['facility']) ?>" required>
    <label>Reason *</label>
    <textarea name="reason" rows="3" required><?= e($r['reason']) ?></textarea>
    <label>Status *</label>
    <select name="status" required>
      <?php foreach (['pending' => 'Pending', 'attended' => 'Attended', 'not_attended' => 'Not attended'] as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Save referral</button>
  </form>
  <p><a href="household_view.php?id=<?= (int)$member['household_id'] ?>">Cancel</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
