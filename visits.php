<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$isAdmin = $_SESSION['role'] === 'admin';
$filters = ['all' => 'All', 'due' => 'Due in 7 days', 'planned' => 'Planned', 'completed' => 'Completed', 'missed' => 'Missed'];
$filter = $_GET['filter'] ?? 'due';
if (!isset($filters[$filter])) $filter = 'due';

$sql = 'SELECT v.*, h.household_code, h.head_name, u.full_name AS chv_name
        FROM visits v
        JOIN households h ON h.household_id = v.household_id
        JOIN users u ON u.user_id = v.chv_id WHERE 1=1';
$params = [];
if (!$isAdmin) {
    $sql .= ' AND v.chv_id = ?';
    $params[] = $_SESSION['user_id'];
}
if ($filter === 'due') {
    $sql .= " AND v.status = 'planned' AND v.visit_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter !== 'all') {
    $sql .= ' AND v.status = ?';
    $params[] = $filter;
}
$sql .= in_array($filter, ['due', 'planned'], true) ? ' ORDER BY v.visit_date ASC' : ' ORDER BY v.visit_date DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$today = date('Y-m-d');

$pageTitle = 'Visits';
require __DIR__ . '/includes/header.php';
?>
<div class="row-between">
  <h1>Follow-up visits</h1>
  <?php if (!$isAdmin): ?><a class="btn-link" href="visit_form.php">+ Record / plan visit</a><?php endif; ?>
</div>

<div class="tabs">
  <?php foreach ($filters as $key => $label): ?>
    <a href="visits.php?filter=<?= e($key) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
<table>
  <tr><th>Date</th><th>Household</th><th>Status</th><th>Notes</th><th>Next visit</th><?php if ($isAdmin): ?><th>CHV</th><?php endif; ?><th></th></tr>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">No visits found for this filter.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['visit_date']) ?></td>
    <td><a href="household_view.php?id=<?= (int)$r['household_id'] ?>"><?= e($r['household_code']) ?></a> <?= e($r['head_name']) ?></td>
    <td>
      <?= e(ucfirst($r['status'])) ?>
      <?php if ($r['status'] === 'planned' && $r['visit_date'] < $today): ?><span class="badge red">Overdue</span><?php endif; ?>
    </td>
    <td><?= e(mb_strimwidth((string)$r['notes'], 0, 60, '...')) ?></td>
    <td><?= e($r['next_visit_date'] ?: '-') ?></td>
    <?php if ($isAdmin): ?><td><?= e($r['chv_name']) ?></td><?php endif; ?>
    <td><?php if (!$isAdmin): ?><a href="visit_form.php?id=<?= (int)$r['visit_id'] ?>">Update</a><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
