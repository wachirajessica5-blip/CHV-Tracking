<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$isAdmin = $_SESSION['role'] === 'admin';
$filters = ['due' => 'Due', 'overdue' => 'Overdue', 'given' => 'Given', 'missed' => 'Missed', 'all' => 'All'];
$filter = $_GET['filter'] ?? 'due';
if (!isset($filters[$filter])) $filter = 'due';

$sql = 'SELECT vc.*, m.full_name AS member_name, m.household_id, h.household_code, u.full_name AS chv_name
        FROM vaccinations vc
        JOIN household_members m ON m.member_id = vc.member_id
        JOIN households h ON h.household_id = m.household_id
        JOIN users u ON u.user_id = h.chv_id WHERE 1=1';
$params = [];
if (!$isAdmin) {
    $sql .= ' AND h.chv_id = ?';
    $params[] = $_SESSION['user_id'];
}
if ($filter === 'due') {
    $sql .= " AND vc.status = 'due'";
} elseif ($filter === 'overdue') {
    $sql .= " AND vc.status = 'due' AND vc.due_date < CURDATE()";
} elseif ($filter !== 'all') {
    $sql .= ' AND vc.status = ?';
    $params[] = $filter;
}
$sql .= in_array($filter, ['due', 'overdue'], true) ? ' ORDER BY vc.due_date ASC' : ' ORDER BY vc.due_date DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$today = date('Y-m-d');

$pageTitle = 'Vaccinations';
require __DIR__ . '/includes/header.php';
?>
<div class="row-between">
  <h1>Vaccination milestones</h1>
</div>
<?php if (!$isAdmin): ?>
  <p class="muted">To record a vaccination, open a household and click <strong>Vaccinate</strong> next to the member.</p>
<?php endif; ?>

<div class="tabs">
  <?php foreach ($filters as $key => $label): ?>
    <a href="vaccinations.php?filter=<?= e($key) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
<table>
  <tr><th>Member</th><th>Household</th><th>Vaccine</th><th>Due date</th><th>Status</th><th>Date given</th><?php if ($isAdmin): ?><th>CHV</th><?php endif; ?><th></th></tr>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">No vaccination records for this filter.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['member_name']) ?></td>
    <td><a href="household_view.php?id=<?= (int)$r['household_id'] ?>"><?= e($r['household_code']) ?></a></td>
    <td><?= e($r['vaccine_name']) ?></td>
    <td><?= e($r['due_date']) ?></td>
    <td>
      <?= e(ucfirst($r['status'])) ?>
      <?php if ($r['status'] === 'due' && $r['due_date'] < $today): ?><span class="badge red">Overdue</span><?php endif; ?>
    </td>
    <td><?= e($r['date_given'] ?: '-') ?></td>
    <?php if ($isAdmin): ?><td><?= e($r['chv_name']) ?></td><?php endif; ?>
    <td><?php if (!$isAdmin): ?><a href="vaccination_form.php?id=<?= (int)$r['vaccination_id'] ?>">Update</a><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
