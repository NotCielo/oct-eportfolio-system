<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo   = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_year') {
        $name = trim($_POST['year_name'] ?? '');
        if (!preg_match('/^\d{4}-\d{4}$/', $name)) {
            $error = 'Format must be YYYY-YYYY (e.g. 2025-2026).';
        } else {
            $pdo->prepare("INSERT INTO academic_years (name) VALUES (?)")->execute([$name]);
            $success = "Academic year {$name} created.";
        }

    } elseif ($action === 'create_semester') {
        $year_id = (int)($_POST['year_id'] ?? 0);
        $sem     = $_POST['sem_name'] ?? '';
        $valid   = ['1st Semester','2nd Semester','Summer'];
        if (!$year_id || !in_array($sem, $valid, true)) {
            $error = 'Invalid semester data.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM semesters WHERE academic_year_id=? AND name=?");
            $dup->execute([$year_id, $sem]);
            if ($dup->fetch()) {
                $error = 'That semester already exists for this year.';
            } else {
                $pdo->prepare("INSERT INTO semesters (academic_year_id, name) VALUES (?,?)")->execute([$year_id, $sem]);
                $success = "{$sem} created.";
            }
        }

    } elseif ($action === 'activate_semester') {
        $sem_id = (int)($_POST['sem_id'] ?? 0);
        if ($sem_id) {
            $pdo->query("UPDATE semesters SET is_active=0");
            $pdo->query("UPDATE academic_years SET is_active=0");
            $pdo->prepare("UPDATE semesters SET is_active=1, is_locked=0 WHERE id=?")->execute([$sem_id]);
            $yearId = $pdo->prepare("SELECT academic_year_id FROM semesters WHERE id=?");
            $yearId->execute([$sem_id]);
            $yearId = $yearId->fetchColumn();
            $pdo->prepare("UPDATE academic_years SET is_active=1 WHERE id=?")->execute([$yearId]);
            $success = 'Semester activated. Previous semesters are now view-only.';
        }

    } elseif ($action === 'lock_semester') {
        $sem_id = (int)($_POST['sem_id'] ?? 0);
        if ($sem_id) {
            $pdo->prepare("UPDATE semesters SET is_locked=1, is_active=0 WHERE id=?")->execute([$sem_id]);
            $success = 'Semester locked.';
        }
    }
}

$years = $pdo->query(
    "SELECT ay.*, 
            (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id=ay.id) AS sem_count
     FROM academic_years ay ORDER BY ay.name DESC"
)->fetchAll();

$semesters = $pdo->query(
    "SELECT s.*, ay.name AS year_name
     FROM semesters s JOIN academic_years ay ON ay.id=s.academic_year_id
     ORDER BY ay.name DESC, s.id DESC"
)->fetchAll();

include 'header.php';
?>

<div class="mb-lg">
    <h2 class="font-headline-md text-headline-md text-on-surface">Academic Years & Semesters</h2>
    <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Manage the school calendar and activate semesters.</p>
</div>

<?php if ($success): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">check_circle</span><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-lg px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
    <span class="material-symbols-outlined text-sm">error</span><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-lg mb-lg">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Create Academic Year</h3>
        <form method="POST" class="space-y-md">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_year"/>
            <input type="text" name="year_name" placeholder="e.g. 2025-2026" maxlength="9"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" required/>
            <button type="submit"
                class="w-full h-10 rounded-full bg-primary text-white font-label-lg text-label-lg hover:bg-green-800 transition-colors">
                Create Year
            </button>
        </form>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg shadow-sm">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Add Semester</h3>
        <form method="POST" class="space-y-md">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_semester"/>
            <select name="year_id"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all appearance-none" required>
                <option value="">Select Academic Year</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sem_name"
                class="w-full border border-outline-variant rounded-lg p-2 text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all appearance-none" required>
                <option value="">Select Semester</option>
                <option>1st Semester</option>
                <option>2nd Semester</option>
                <option>Summer</option>
            </select>
            <button type="submit"
                class="w-full h-10 rounded-full bg-primary text-white font-label-lg text-label-lg hover:bg-green-800 transition-colors">
                Add Semester
            </button>
        </form>
    </div>
</div>

<h3 class="font-label-lg text-label-lg text-outline uppercase tracking-wider mb-md">All Semesters</h3>
<div class="space-y-sm pb-24">
    <?php if (empty($semesters)): ?>
    <p class="text-center text-on-surface-variant py-8 text-sm">No semesters yet. Create an academic year first.</p>
    <?php else: ?>
    <?php foreach ($semesters as $s): ?>
    <div class="flex items-center gap-md p-md bg-white rounded-xl card-border bento-shadow">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <p class="font-label-lg text-label-lg text-on-surface"><?= htmlspecialchars($s['year_name']) ?> — <?= htmlspecialchars($s['name']) ?></p>
                <?php if ($s['is_active']): ?>
                <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold uppercase">Active</span>
                <?php elseif ($s['is_locked']): ?>
                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold uppercase">Locked</span>
                <?php else: ?>
                <span class="px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 text-[10px] font-bold uppercase">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-sm shrink-0">
            <?php if (!$s['is_active'] && !$s['is_locked']): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="activate_semester"/>
                <input type="hidden" name="sem_id" value="<?= $s['id'] ?>"/>
                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-primary text-white hover:bg-green-800 transition-colors">
                    Activate
                </button>
            </form>
            <?php endif; ?>
            <?php if ($s['is_active']): ?>
            <form method="POST" onsubmit="return confirm('Lock this semester? It will become read-only.')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="lock_semester"/>
                <input type="hidden" name="sem_id" value="<?= $s['id'] ?>"/>
                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-error/30 text-error hover:bg-red-50 transition-colors">
                    Lock
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</main>
<?php include 'footer.php'; ?>
