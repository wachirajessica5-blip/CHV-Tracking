<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$isAdmin = $_SESSION['role'] === 'admin';
$q = trim($_GET['q'] ?? '');

$sql = 'SELECT h.*, u.full_name AS chv_name,
        (SELECT COUNT(*) FROM household_members m WHERE m.household_id = h.household_id) AS member_count
        FROM households h JOIN users u ON u.user_id = h.chv_id WHERE 1=1';
$params = [];
if (!$isAdmin) {
    $sql .= ' AND h.chv_id = ?';
    $params[] = $_SESSION['user_id'];
}
if ($q !== '') {
    $sql .= ' AND (h.household_code LIKE ? OR h.head_name LIKE ? OR h.village LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY h.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Households';
require __DIR__ . '/includes/header.php';
?>
<div class="row-between">
  <h1>Households</h1>
  <?php if (!$isAdmin): ?><a class="btn-link" href="household_form.php">+ Register household</a><?php endif; ?>
</div>

<form method="get" class="searchbar">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by code, head of household or village">
  <button type="submit" class="btn small">Search</button>
</form>

<div class="table-wrap">
<table>
  <tr><th>Code</th><th>Head of household</th><th>Village</th><th>Members</th><?php if ($isAdmin): ?><th>CHV</th><?php endif; ?><th></th></tr>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="muted">No households found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['household_code']) ?></td>
    <td><?= e($r['head_name']) ?></td>
    <td><?= e($r['village']) ?></td>
    <td><?= (int)$r['member_count'] ?></td>
    <?php if ($isAdmin): ?><td><?= e($r['chv_name']) ?></td><?php endif; ?>
    <td><a href="household_view.php?id=<?= (int)$r['household_id'] ?>">Open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
