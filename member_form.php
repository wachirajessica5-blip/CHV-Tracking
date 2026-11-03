<?php
// Add (?household_id=) or edit (?id=) a household member. CHV who owns the household only.
require_once __DIR__ . '/includes/auth.php';
require_role('chv');

$memberId = (int)($_GET['id'] ?? 0);
$m = ['full_name' => '', 'gender' => '', 'date_of_birth' => '', 'relationship' => ''];

if ($memberId) {
    $s = db()->prepare('SELECT * FROM household_members WHERE member_id = ?');
    $s->execute([$memberId]);
    $m = $s->fetch();
    if (!$m) { http_response_code(404); die('Member not found.'); }
    $householdId = (int)$m['household_id'];
} else {
    $householdId = (int)($_GET['household_id'] ?? 0);
}
$h = load_household($householdId, true); // enforces ownership

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $m['full_name']     = trim($_POST['full_name'] ?? '');
    $m['gender']        = $_POST['gender'] ?? '';
    $m['date_of_birth'] = $_POST['date_of_birth'] ?? '';
    $m['relationship']  = trim($_POST['relationship'] ?? '');

    if ($m['full_name'] === '') $errors[] = 'Full name is required.';
    if (!in_array($m['gender'], ['male', 'female'], true)) $errors[] = 'Select a gender.';
    $dob = DateTime::createFromFormat('Y-m-d', $m['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $m['date_of_birth']) {
        $errors[] = 'Enter a valid date of birth.';
    } elseif ($dob > new DateTime('today')) {
        $errors[] = 'Date of birth cannot be in the future.';
    }
    if ($m['relationship'] === '') $errors[] = 'Relationship to head of household is required.';

    if (!$errors) {
        if ($memberId) {
            db()->prepare('UPDATE household_members SET full_name=?, gender=?, date_of_birth=?, relationship=? WHERE member_id=?')
                ->execute([$m['full_name'], $m['gender'], $m['date_of_birth'], $m['relationship'], $memberId]);
        } else {
            db()->prepare('INSERT INTO household_members (household_id, full_name, gender, date_of_birth, relationship) VALUES (?,?,?,?,?)')
                ->execute([$householdId, $m['full_name'], $m['gender'], $m['date_of_birth'], $m['relationship']]);
        }
        header('Location: household_view.php?id=' . $householdId);
        exit;
    }
}

$pageTitle = $memberId ? 'Edit member' : 'Add member';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($pageTitle) ?> <span class="muted">&ndash; <?= e($h['household_code']) ?></span></h1>
<div class="card form-card">
  <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Full name *</label>
    <input type="text" name="full_name" value="<?= e($m['full_name']) ?>" required>
    <label>Gender *</label>
    <select name="gender" required>
      <option value="">Select...</option>
      <option value="male" <?= $m['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
      <option value="female" <?= $m['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
    </select>
    <label>Date of birth *</label>
    <input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= e($m['date_of_birth']) ?>" required>
    <label>Relationship to head of household *</label>
    <input type="text" name="relationship" value="<?= e($m['relationship']) ?>" placeholder="e.g. Spouse, Child, Parent" required>
    <button type="submit" class="btn">Save member</button>
  </form>
  <p><a href="household_view.php?id=<?= $householdId ?>">Cancel</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
