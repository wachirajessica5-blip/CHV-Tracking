<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$h = load_household($id);
$isOwner = (int)$h['chv_id'] === (int)$_SESSION['user_id'];

$stmt = db()->prepare('SELECT * FROM household_members WHERE household_id = ? ORDER BY date_of_birth ASC');
$stmt->execute([$id]);
$members = $stmt->fetchAll();

function age_text(string $dob): string {
    $d = new DateTime($dob);
    $diff = $d->diff(new DateTime('today'));
    return $diff->y >= 2 ? $diff->y . ' yrs' : ($diff->y * 12 + $diff->m) . ' months';
}

$pageTitle = 'Household ' . $h['household_code'];
require __DIR__ . '/includes/header.php';
?>
<div class="row-between">
  <h1><?= e($h['household_code']) ?> &ndash; <?= e($h['head_name']) ?></h1>
  <?php if ($isOwner): ?><a class="btn-link" href="household_form.php?id=<?= $id ?>">Edit household</a><?php endif; ?>
</div>

<div class="card">
  <p><strong>Village:</strong> <?= e($h['village']) ?></p>
  <p><strong>Phone:</strong> <?= e($h['phone'] ?: '-') ?></p>
  <p><strong>Notes:</strong> <?= e($h['notes'] ?: '-') ?></p>
  <p><strong>Registered by:</strong> <?= e($h['chv_name']) ?></p>
</div>

<div class="row-between" style="margin-top:24px">
  <h2>Household members</h2>
  <?php if ($isOwner): ?><a class="btn-link" href="member_form.php?household_id=<?= $id ?>">+ Add member</a><?php endif; ?>
</div>
<div class="table-wrap">
<table>
  <tr><th>Name</th><th>Gender</th><th>Date of birth</th><th>Age</th><th>Relationship</th><?php if ($isOwner): ?><th></th><?php endif; ?></tr>
  <?php if (!$members): ?><tr><td colspan="6" class="muted">No members recorded yet.</td></tr><?php endif; ?>
  <?php foreach ($members as $m): ?>
  <tr>
    <td><?= e($m['full_name']) ?></td>
    <td><?= e(ucfirst($m['gender'])) ?></td>
    <td><?= e($m['date_of_birth']) ?></td>
    <td><?= e(age_text($m['date_of_birth'])) ?></td>
    <td><?= e($m['relationship']) ?></td>
    <?php if ($isOwner): ?><td>
      <a href="member_form.php?id=<?= (int)$m['member_id'] ?>">Edit</a> &middot;
      <a href="vaccination_form.php?member_id=<?= (int)$m['member_id'] ?>">Vaccinate</a> &middot;
      <a href="referral_form.php?member_id=<?= (int)$m['member_id'] ?>">Refer</a>
    </td><?php endif; ?>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php
$vs = db()->prepare('SELECT * FROM visits WHERE household_id = ? ORDER BY visit_date DESC LIMIT 10');
$vs->execute([$id]);
$visits = $vs->fetchAll();
?>
<div class="row-between" style="margin-top:24px">
  <h2>Recent visits</h2>
  <?php if ($isOwner): ?><a class="btn-link" href="visit_form.php?household_id=<?= $id ?>">+ Record / plan visit</a><?php endif; ?>
</div>
<div class="table-wrap">
<table>
  <tr><th>Date</th><th>Status</th><th>Notes</th><th>Next visit</th></tr>
  <?php if (!$visits): ?><tr><td colspan="4" class="muted">No visits recorded yet.</td></tr><?php endif; ?>
  <?php foreach ($visits as $vr): ?>
  <tr>
    <td><?= e($vr['visit_date']) ?></td>
    <td><?= e(ucfirst($vr['status'])) ?></td>
    <td><?= e($vr['notes'] ?: '-') ?></td>
    <td><?= e($vr['next_visit_date'] ?: '-') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php
$vq = db()->prepare('SELECT vc.*, m.full_name FROM vaccinations vc JOIN household_members m ON m.member_id = vc.member_id
                     WHERE m.household_id = ? ORDER BY vc.due_date DESC LIMIT 10');
$vq->execute([$id]);
$vaccs = $vq->fetchAll();
$rq = db()->prepare('SELECT r.*, m.full_name FROM referrals r JOIN household_members m ON m.member_id = r.member_id
                     WHERE m.household_id = ? ORDER BY r.referral_date DESC LIMIT 10');
$rq->execute([$id]);
$refs = $rq->fetchAll();
$todayStr = date('Y-m-d');
?>
<h2 style="margin-top:24px">Vaccinations</h2>
<div class="table-wrap">
<table>
  <tr><th>Member</th><th>Vaccine</th><th>Due</th><th>Status</th><th>Given</th></tr>
  <?php if (!$vaccs): ?><tr><td colspan="5" class="muted">No vaccination records yet.</td></tr><?php endif; ?>
  <?php foreach ($vaccs as $vc): ?>
  <tr>
    <td><?= e($vc['full_name']) ?></td>
    <td><?= e($vc['vaccine_name']) ?></td>
    <td><?= e($vc['due_date']) ?></td>
    <td><?= e(ucfirst($vc['status'])) ?><?php if ($vc['status'] === 'due' && $vc['due_date'] < $todayStr): ?><span class="badge red">Overdue</span><?php endif; ?></td>
    <td><?= e($vc['date_given'] ?: '-') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>

<h2 style="margin-top:24px">Referrals</h2>
<div class="table-wrap">
<table>
  <tr><th>Date</th><th>Member</th><th>Facility</th><th>Reason</th><th>Status</th></tr>
  <?php if (!$refs): ?><tr><td colspan="5" class="muted">No referrals yet.</td></tr><?php endif; ?>
  <?php foreach ($refs as $rf): ?>
  <tr>
    <td><?= e($rf['referral_date']) ?></td>
    <td><?= e($rf['full_name']) ?></td>
    <td><?= e($rf['facility']) ?></td>
    <td><?= e($rf['reason']) ?></td>
    <td><?= e(ucfirst(str_replace('_', ' ', $rf['status']))) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<p><a href="households.php">&larr; Back to households</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
