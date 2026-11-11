<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$pdo     = getDB();
$uid     = currentUserId();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_password') {
        $tid      = (int)($_POST['uid'] ?? 0);
        $new_pass = trim($_POST['new_password'] ?? '');
        if (!$tid || strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='student'");
            $chk->execute([$tid]);
            if (!$chk->fetch()) { $error = 'Invalid student account.'; }
            else {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $tid]);
                $success = 'Password reset successfully.';
            }
        }
    }
}

$subjectIds = $pdo->prepare("SELECT id FROM subjects WHERE coordinator_id=? AND is_archived=0");
$subjectIds->execute([$uid]);
$subjectIds = array_column($subjectIds->fetchAll(), 'id');

$resetUser = null;
if (isset($_GET['reset'])) {
    $ru = $pdo->prepare("SELECT id, full_name, school_id FROM users WHERE id=? AND role='student'");
    $ru->execute([(int)$_GET['reset']]);
    $resetUser = $ru->fetch();
}

$search = trim($_GET['q'] ?? '');
$tab    = $_GET['tab'] ?? 'students';

include 'header.php';
?>

<div class="mb-lg">
    <h2 class="font-headline-md text-headline-md text-on-surface">Users</h2>
    <p class="text-sm text-on-surface-variant">View faculty and students under your subjects.</p>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($resetUser): ?>
<div class="mb-lg bg-surface-container-lowest border border-primary/30 rounded-xl p-lg shadow-sm">
    <h3 class="font-title-lg text-on-surface mb-1">Reset Password — <?= htmlspecialchars($resetUser['full_name']) ?></h3>
    <p class="text-sm text-on-surface-variant mb-md"><?= htmlspecialchars($resetUser['school_id']) ?></p>
    <form method="POST" class="flex gap-sm flex-wrap">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_password"/>
        <input type="hidden" name="uid" value="<?= $resetUser['id'] ?>"/>
        <input type="text" name="new_password" placeholder="New temporary password" minlength="6"
            class="flex-1 border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary" required/>
        <button type="submit" class="px-lg h-10 rounded-full bg-primary text-white font-label-lg hover:bg-green-800 transition-colors">Set Password</button>
        <a href="users.php" class="px-lg h-10 rounded-full border border-outline-variant text-on-surface-variant font-label-lg flex items-center hover:bg-surface-container-low transition-colors">Cancel</a>
    </form>
</div>
<?php endif; ?>

<div class="flex gap-sm mb-lg">
    <a href="?tab=students" class="px-4 py-2 rounded-full text-xs font-medium border transition-colors <?= $tab==='students' ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant' ?>">Students</a>
    <a href="?tab=faculty"  class="px-4 py-2 rounded-full text-xs font-medium border transition-colors <?= $tab==='faculty'  ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant' ?>">Faculty</a>
</div>

<?php if (empty($subjectIds)): ?>
<p class="text-center text-on-surface-variant py-16 text-sm">No subjects assigned yet.</p>
<?php else: ?>

<?php if ($tab === 'students'): ?>
<?php
$in  = implode(',', array_fill(0, count($subjectIds), '?'));
$sql = "SELECT DISTINCT u.id, u.full_name, u.school_id, u.year_level FROM enrollments e
        JOIN users u ON u.id=e.student_id
        WHERE e.subject_id IN ($in)";
$params = $subjectIds;
if ($search !== '') { $sql .= " AND (u.full_name LIKE ? OR u.school_id LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY u.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$people = $stmt->fetchAll();
?>
<div class="relative mb-md">
    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
    <form method="GET">
        <input type="hidden" name="tab" value="students"/>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student..."
            class="w-full pl-9 pr-4 py-2 rounded-xl border border-outline-variant text-sm focus:outline-none focus:border-primary"/>
    </form>
</div>
<div class="space-y-sm pb-24">
    <?php if (empty($people)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No students found.</p>
    <?php else: ?>
    <?php foreach ($people as $p): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">person</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($p['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($p['school_id']) ?><?= $p['year_level'] ? ' · ' . strtoupper($p['year_level']) : '' ?></p>
        </div>
        <a href="?tab=students&reset=<?= $p['id'] ?>#reset"
           class="text-[10px] px-2 py-1 rounded-lg border border-outline-variant hover:bg-surface-container-low transition-colors text-on-surface-variant shrink-0">
            Reset PW
        </a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php else: ?>
<?php
$in  = implode(',', array_fill(0, count($subjectIds), '?'));
$sql = "SELECT DISTINCT u.id, u.full_name, u.school_id,
               COUNT(DISTINCT s2.id) AS subject_count
        FROM subjects s2 JOIN users u ON u.id=s2.faculty_id
        WHERE s2.id IN ($in) GROUP BY u.id ORDER BY u.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($subjectIds);
$people = $stmt->fetchAll();
?>
<div class="space-y-sm pb-24">
    <?php if (empty($people)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No faculty found.</p>
    <?php else: ?>
    <?php foreach ($people as $p): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-sm">badge</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-label-lg text-label-lg text-on-surface truncate"><?= htmlspecialchars($p['full_name']) ?></p>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= htmlspecialchars($p['school_id']) ?> · <?= $p['subject_count'] ?> subject<?= $p['subject_count']!=1?'s':'' ?></p>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</main>
<?php include 'footer.php'; ?>
