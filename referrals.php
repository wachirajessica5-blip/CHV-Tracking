<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$isAdmin = $_SESSION['role'] === 'admin';
$filters = ['pending' => 'Pending', 'attended' => 'Attended', 'not_attended' => 'Not attended', 'all' => 'All'];
$filter = $_GET['filter'] ?? 'pending';
if (!isset($filters[$filter])) $filter = 'pending';

$sql = 'SELECT r.*, m.full_name AS member_name, m.household_id, h.household_code, u.full_name AS chv_name
        FROM referrals r
        JOIN household_members m ON m.member_id = r.member_id
        JOIN households h ON h.household_id = m.household_id
        JOIN users u ON u.user_id = r.chv_id WHERE 1=1';
$params = [];
if (!$isAdmin) {
    $sql .= ' AND r.chv_id = ?';
    $params[] = $_SESSION['user_id'];
}
if ($filter !== 'all') {
    $sql .= ' AND r.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY r.referral_date DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Referrals';
require __DIR__ . '/includes/header.php';
?>
<h1>Referrals</h1>
<?php if (!$isAdmin): ?>
  <p class="muted">To make a referral, open a household and click <strong>Refer</strong> next to the member.</p>
<?php endif; ?>

<div class="tabs">
  <?php foreach ($filters as $key => $label): ?>
    <a href="referrals.php?filter=<?= e($key) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
<table>
  <tr><th>Date</th><th>Member</th><th>Household</th><th>Facility</th><th>Reason</th><th>Status</th><?php if ($isAdmin): ?><th>CHV</th><?php endif; ?><th></th></tr>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">No referrals for this filter.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['referral_date']) ?></td>
    <td><?= e($r['member_name']) ?></td>
    <td><a href="household_view.php?id=<?= (int)$r['household_id'] ?>"><?= e($r['household_code']) ?></a></td>
    <td><?= e($r['facility']) ?></td>
    <td><?= e($r['reason']) ?></td>
    <td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
    <?php if ($isAdmin): ?><td><?= e($r['chv_name']) ?></td><?php endif; ?>
    <td><?php if (!$isAdmin): ?><a href="referral_form.php?id=<?= (int)$r['referral_id'] ?>">Update</a><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
