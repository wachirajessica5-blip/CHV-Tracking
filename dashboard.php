<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();
$uid = $_SESSION['user_id'];
$isAdmin = $_SESSION['role'] === 'admin';

function one(PDO $pdo, string $sql, array $params = []): int {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return (int)$s->fetchColumn();
}

if ($isAdmin) {
    $cards = [
        'Active CHVs'             => one($pdo, "SELECT COUNT(*) FROM users WHERE role='chv' AND status='active'"),
        'Households'              => one($pdo, 'SELECT COUNT(*) FROM households'),
        'Visits this month'       => one($pdo, "SELECT COUNT(*) FROM visits WHERE status='completed' AND YEAR(visit_date)=YEAR(CURDATE()) AND MONTH(visit_date)=MONTH(CURDATE())"),
        'Pending referrals'       => one($pdo, "SELECT COUNT(*) FROM referrals WHERE status='pending'"),
        'Overdue vaccinations'    => one($pdo, "SELECT COUNT(*) FROM vaccinations WHERE status='due' AND due_date < CURDATE()"),
    ];
    $pageTitle = 'Administrator Dashboard';
} else {
    $cards = [
        'My households'           => one($pdo, 'SELECT COUNT(*) FROM households WHERE chv_id=?', [$uid]),
        'Household members'       => one($pdo, 'SELECT COUNT(*) FROM household_members m JOIN households h ON h.household_id=m.household_id WHERE h.chv_id=?', [$uid]),
        'Follow-ups due (7 days)' => one($pdo, "SELECT COUNT(*) FROM visits WHERE chv_id=? AND status='planned' AND visit_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)", [$uid]),
        'Pending referrals'       => one($pdo, "SELECT COUNT(*) FROM referrals WHERE chv_id=? AND status='pending'", [$uid]),
        'Overdue vaccinations'    => one($pdo, "SELECT COUNT(*) FROM vaccinations vc JOIN household_members m ON m.member_id=vc.member_id JOIN households h ON h.household_id=m.household_id WHERE h.chv_id=? AND vc.status='due' AND vc.due_date < CURDATE()", [$uid]),
    ];
    $pageTitle = 'CHV Dashboard';
}

require __DIR__ . '/includes/header.php';
?>
<h1><?= e($pageTitle) ?></h1>
<p class="muted">Welcome, <?= e($_SESSION['full_name']) ?>.</p>
<div class="grid">
  <?php foreach ($cards as $label => $count): ?>
    <div class="card stat">
      <div class="num"><?= $count ?></div>
      <div class="label"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$isAdmin):
  $up = $pdo->prepare("SELECT v.visit_date, h.household_code, h.head_name FROM visits v JOIN households h ON h.household_id = v.household_id
                       WHERE v.chv_id = ? AND v.status = 'planned' ORDER BY v.visit_date ASC LIMIT 5");
  $up->execute([$uid]);
  $upcoming = $up->fetchAll();
?>
<div class="row-between" style="margin-top:24px">
  <h2>Scheduled follow-ups</h2>
  <a href="visits.php?filter=planned">View all</a>
</div>
<div class="table-wrap">
<table>
  <tr><th>Date</th><th>Household</th></tr>
  <?php if (!$upcoming): ?><tr><td colspan="2" class="muted">No planned visits.</td></tr><?php endif; ?>
  <?php foreach ($upcoming as $u): ?>
  <tr>
    <td><?= e($u['visit_date']) ?><?php if ($u['visit_date'] < date('Y-m-d')): ?> <span class="badge red">Overdue</span><?php endif; ?></td>
    <td><?= e($u['household_code'] . ' - ' . $u['head_name']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
