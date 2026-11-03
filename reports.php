<?php
// Administrator: monthly summary report built from stored activity data.
require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
$start = $month . '-01';
$end   = date('Y-m-d', strtotime($start . ' +1 month'));
$label = date('F Y', strtotime($start));

$pdo = db();
function cnt(PDO $pdo, string $sql, array $p): int {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int)$s->fetchColumn();
}
$se = [$start, $end];

$sum = [
    'Households registered'       => cnt($pdo, 'SELECT COUNT(*) FROM households WHERE created_at >= ? AND created_at < ?', $se),
    'Household members registered'=> cnt($pdo, 'SELECT COUNT(*) FROM household_members WHERE created_at >= ? AND created_at < ?', $se),
    'Visits completed'            => cnt($pdo, "SELECT COUNT(*) FROM visits WHERE status='completed' AND visit_date >= ? AND visit_date < ?", $se),
    'Visits missed'               => cnt($pdo, "SELECT COUNT(*) FROM visits WHERE status='missed' AND visit_date >= ? AND visit_date < ?", $se),
    'Visits still planned'        => cnt($pdo, "SELECT COUNT(*) FROM visits WHERE status='planned' AND visit_date >= ? AND visit_date < ?", $se),
    'Vaccinations given'          => cnt($pdo, "SELECT COUNT(*) FROM vaccinations WHERE status='given' AND date_given >= ? AND date_given < ?", $se),
    'Vaccinations due (not yet given) this month' => cnt($pdo, "SELECT COUNT(*) FROM vaccinations WHERE status='due' AND due_date >= ? AND due_date < ?", $se),
    'Vaccinations missed'         => cnt($pdo, "SELECT COUNT(*) FROM vaccinations WHERE status='missed' AND due_date >= ? AND due_date < ?", $se),
    'Referrals made'              => cnt($pdo, 'SELECT COUNT(*) FROM referrals WHERE referral_date >= ? AND referral_date < ?', $se),
    'Referrals attended'          => cnt($pdo, "SELECT COUNT(*) FROM referrals WHERE status='attended' AND referral_date >= ? AND referral_date < ?", $se),
    'Referrals pending'           => cnt($pdo, "SELECT COUNT(*) FROM referrals WHERE status='pending' AND referral_date >= ? AND referral_date < ?", $se),
    'Referrals not attended'      => cnt($pdo, "SELECT COUNT(*) FROM referrals WHERE status='not_attended' AND referral_date >= ? AND referral_date < ?", $se),
];

$perChv = $pdo->prepare(
    "SELECT u.full_name,
      (SELECT COUNT(*) FROM households h WHERE h.chv_id = u.user_id AND h.created_at >= ? AND h.created_at < ?) AS hh,
      (SELECT COUNT(*) FROM visits v WHERE v.chv_id = u.user_id AND v.status='completed' AND v.visit_date >= ? AND v.visit_date < ?) AS visits,
      (SELECT COUNT(*) FROM vaccinations vc JOIN household_members m ON m.member_id = vc.member_id
         JOIN households h ON h.household_id = m.household_id
         WHERE h.chv_id = u.user_id AND vc.status='given' AND vc.date_given >= ? AND vc.date_given < ?) AS vacc,
      (SELECT COUNT(*) FROM referrals r WHERE r.chv_id = u.user_id AND r.referral_date >= ? AND r.referral_date < ?) AS refs
     FROM users u WHERE u.role = 'chv' ORDER BY u.full_name"
);
$perChv->execute(array_merge($se, $se, $se, $se));
$chvRows = $perChv->fetchAll();

$pageTitle = 'Monthly Report';
require __DIR__ . '/includes/header.php';
?>
<div class="row-between no-print">
  <h1>Monthly summary report</h1>
  <button class="btn small" onclick="window.print()">Print / Save as PDF</button>
</div>

<form method="get" class="searchbar no-print">
  <input type="month" name="month" value="<?= e($month) ?>">
  <button type="submit" class="btn small">Generate</button>
</form>

<div class="card">
  <h2>Report for <?= e($label) ?></h2>
  <p class="muted">Generated on <?= e(date('d M Y')) ?> by <?= e($_SESSION['full_name']) ?></p>
  <div class="table-wrap">
  <table>
    <tr><th>Indicator</th><th>Count</th></tr>
    <?php foreach ($sum as $name => $n): ?>
      <tr><td><?= e($name) ?></td><td><?= $n ?></td></tr>
    <?php endforeach; ?>
  </table>
  </div>

  <h2 style="margin-top:24px">Activity by CHV</h2>
  <div class="table-wrap">
  <table>
    <tr><th>CHV</th><th>Households registered</th><th>Visits completed</th><th>Vaccinations given</th><th>Referrals made</th></tr>
    <?php if (!$chvRows): ?><tr><td colspan="5" class="muted">No CHV accounts.</td></tr><?php endif; ?>
    <?php foreach ($chvRows as $c): ?>
      <tr>
        <td><?= e($c['full_name']) ?></td>
        <td><?= (int)$c['hh'] ?></td>
        <td><?= (int)$c['visits'] ?></td>
        <td><?= (int)$c['vacc'] ?></td>
        <td><?= (int)$c['refs'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>